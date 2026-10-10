<?php

namespace Tests\Feature\Diablero;

use App\CentralLogics\DiableroLogic;
use App\Models\Cart;
use App\Models\DeliveryMan;
use App\Models\Diablero;
use App\Models\DiableroTask;
use App\Models\Item;
use App\Models\LoadingZone;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Store;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Runs against the local database inside a transaction that is rolled back after each test.
 * Fixtures are built on top of zone 4 / module 1 / store 16 of the seeded data.
 */
abstract class DiableroTestCase extends TestCase
{
    use DatabaseTransactions;

    protected const ZONE_ID = 4;
    protected const MODULE_ID = 1;
    protected const STORE_ID = 16;
    protected const CUSTOMER_LAT = 23.941026;
    protected const CUSTOMER_LNG = -102.705791;

    protected Zone $zone;
    protected LoadingZone $loadingZone;
    protected Store $storeA;
    protected Store $storeB;
    protected Item $itemA;
    protected Item $itemB;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePassportTestKeys();
        Http::fake();
        Mail::fake();

        $this->setting('diablero_service_status', 1);
        $this->setting('diablero_fee', 30);
        $this->setting('diablero_fee_name', 'Diablero Fee');
        $this->setting('diablero_commission', 20);
        $this->setting('cash_on_delivery', json_encode(['status' => 1]));
        $this->setting('digital_payment', json_encode(['status' => 1]));
        config(['order_delivery_verification' => 1, 'canceled_by_store' => 1, 'canceled_by_deliveryman' => 1, 'dm_maximum_orders' => 10]);

        $this->zone = Zone::withoutGlobalScopes()->findOrFail(self::ZONE_ID);
        $this->zone->forceFill(['diablero_status' => 1, 'status' => 1])->save();

        $this->loadingZone = LoadingZone::create([
            'zone_id' => self::ZONE_ID,
            'name' => 'Test loading dock',
            'address' => 'Dock 1',
            'latitude' => self::CUSTOMER_LAT + 0.01,
            'longitude' => self::CUSTOMER_LNG + 0.01,
            'is_default' => 1,
            'status' => 1,
        ]);

        DB::table('module_zone')->where(['zone_id' => self::ZONE_ID, 'module_id' => self::MODULE_ID])->delete();
        DB::table('module_zone')->insert([
            'zone_id' => self::ZONE_ID,
            'module_id' => self::MODULE_ID,
            'per_km_shipping_charge' => 10,
            'minimum_shipping_charge' => 50,
            'maximum_shipping_charge' => null,
            'delivery_charge_type' => 'distance',
            'maximum_cod_order_amount' => null,
        ]);

        $this->storeA = Store::withoutGlobalScopes()->findOrFail(self::STORE_ID);
        $this->storeA->forceFill(['status' => 1, 'active' => 1, 'minimum_order' => 0])->save();
        $this->storeB = $this->storeA->replicate();
        $this->storeB->name = 'Diablero Test Store B';
        $this->storeB->slug = 'diablero-test-store-b-' . Str::random(6);
        $this->storeB->phone = '+52' . random_int(1000000000, 9999999999);
        $this->storeB->email = 'store-b-' . Str::random(6) . '@example.com';
        $this->storeB->save();
        foreach (DB::table('store_schedule')->where('store_id', self::STORE_ID)->get() as $schedule) {
            DB::table('store_schedule')->insert([
                'store_id' => $this->storeB->id,
                'day' => $schedule->day,
                'opening_time' => $schedule->opening_time,
                'closing_time' => $schedule->closing_time,
            ]);
        }

        $this->itemA = Item::withoutGlobalScopes()->where('store_id', self::STORE_ID)->firstOrFail();
        $this->itemB = $this->itemA->replicate();
        $this->itemB->store_id = $this->storeB->id;
        $this->itemB->slug = 'diablero-test-item-' . Str::random(6);
        $this->itemB->save();

        $this->customer = User::findOrFail(2);
        $this->customer->forceFill(['wallet_balance' => 100000, 'status' => 1])->save();
        Cart::where('user_id', $this->customer->id)->delete();
    }

    private function usePassportTestKeys(): void
    {
        $dir = sys_get_temp_dir() . '/abastos-passport-test-keys';
        if (!is_file($dir . '/oauth-private.key')) {
            @mkdir($dir, 0700, true);
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            file_put_contents($dir . '/oauth-private.key', $private);
            file_put_contents($dir . '/oauth-public.key', openssl_pkey_get_details($key)['key']);
            chmod($dir . '/oauth-private.key', 0600);
            chmod($dir . '/oauth-public.key', 0600);
        }
        Passport::loadKeysFrom($dir);
    }

    protected function setting(string $key, $value): void
    {
        DB::table('business_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    protected function addToCart(Item $item, int $qty = 1): Cart
    {
        return Cart::create([
            'user_id' => $this->customer->id,
            'module_id' => self::MODULE_ID,
            'item_id' => $item->id,
            'is_guest' => 0,
            'add_on_ids' => '[]',
            'add_on_qtys' => '[]',
            'item_type' => 'App\Models\Item',
            'price' => $item->price,
            'quantity' => $qty,
            'variation' => '[]',
        ]);
    }

    protected function customerHeaders(): array
    {
        $this->actingAs($this->customer, 'api');

        return ['Authorization' => 'Bearer test', 'moduleId' => self::MODULE_ID, 'zoneId' => json_encode([self::ZONE_ID])];
    }

    protected function placeGroup(array $overrides = [])
    {
        return $this->postJson('/api/v1/customer/order/place-group', array_merge([
            'payment_method' => 'wallet',
            'order_type' => 'delivery',
            'address' => 'Customer street 1',
            'latitude' => self::CUSTOMER_LAT,
            'longitude' => self::CUSTOMER_LNG,
            'contact_person_name' => 'Test',
            'contact_person_number' => '+524625090577',
            'address_type' => 'home',
            'dm_tips' => 0,
        ], $overrides), $this->customerHeaders());
    }

    /** Places a two-store group order through the API and returns its group. */
    protected function placeTwoStoreGroup(): OrderGroup
    {
        $this->addToCart($this->itemA, 2);
        $this->addToCart($this->itemB, 1);
        $response = $this->placeGroup();
        $response->assertOk();

        return OrderGroup::findOrFail($response->json('order_group_id'));
    }

    protected function setOrder(Order $order, array $attributes): Order
    {
        $order->forceFill($attributes)->save();

        return $order;
    }

    protected function groupOrders(OrderGroup $group)
    {
        return Order::withoutGlobalScopes()->where('order_group_id', $group->id)->orderBy('id')->get();
    }

    protected function task(OrderGroup $group): DiableroTask
    {
        return DiableroTask::where('order_group_id', $group->id)->firstOrFail();
    }

    /** Simulates every store marking its order ready. */
    protected function markAllReady(OrderGroup $group): void
    {
        foreach ($this->groupOrders($group) as $order) {
            if (in_array($order->order_status, ['canceled', 'failed'])) {
                continue;
            }
            $order->order_status = 'handover';
            $order->handover = now();
            $order->save();
            DiableroLogic::syncOrder($order, DiableroLogic::ACTOR_VENDOR, $order->store?->vendor_id);
        }
    }

    protected function makeDiablero(array $attrs = []): Diablero
    {
        return Diablero::create(array_merge([
            'f_name' => 'Diablero',
            'l_name' => Str::random(4),
            'phone' => '+52' . random_int(1000000000, 9999999999),
            'password' => bcrypt('secret123'),
            'auth_token' => Str::random(60),
            'zone_id' => self::ZONE_ID,
            'status' => 1,
            'active' => 1,
            'application_status' => 'approved',
        ], $attrs));
    }

    protected function diableroHeaders(Diablero $diablero): array
    {
        return ['Authorization' => 'Bearer ' . $diablero->auth_token];
    }

    protected function makeDeliveryMan(): DeliveryMan
    {
        $dm = DeliveryMan::withoutGlobalScopes()->findOrFail(2);
        $dm->forceFill([
            'auth_token' => Str::random(60), 'active' => 1, 'status' => 1, 'current_orders' => 0,
            'zone_id' => self::ZONE_ID, 'type' => 'zone_wise', 'application_status' => 'approved',
        ])->save();
        DB::table('delivery_man_wallets')->where('delivery_man_id', $dm->id)->update(['collected_cash' => 0]);

        return $dm->fresh();
    }

    /** Drives a group through accept, collection and transit, returning the diablero. */
    protected function driveToInTransit(OrderGroup $group): Diablero
    {
        $this->markAllReady($group);
        $diablero = $this->makeDiablero();
        $task = $this->task($group);
        $h = $this->diableroHeaders($diablero);
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/accept", [], $h)->assertOk();
        foreach ($task->stops as $stop) {
            $this->postJson("/api/v1/diablero/tasks/{$task->id}/stops/{$stop->id}/collect", [], $h)->assertOk();
        }
        $this->postJson("/api/v1/diablero/tasks/{$task->id}/status", ['status' => 'in_transit'], $h)->assertOk();

        return $diablero;
    }
}
