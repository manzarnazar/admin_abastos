<?php

namespace App\CentralLogics;

use App\Models\BusinessSetting;
use App\Models\DeliveryMan;
use App\Models\Diablero;
use App\Models\DiableroTask;
use App\Models\DiableroTaskLog;
use App\Models\DiableroTaskRejection;
use App\Models\DiableroTaskStop;
use App\Models\DiableroWallet;
use App\Models\DiableroWalletTransaction;
use App\Models\LoadingZone;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Zone;
use App\Scopes\ZoneScope;
use Illuminate\Support\Facades\DB;

class DiableroLogic
{
    public const MAX_ONGOING_TASKS = 3;

    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_DIABLERO = 'diablero';
    public const ACTOR_DELIVERYMAN = 'deliveryman';
    public const ACTOR_VENDOR = 'vendor';
    public const ACTOR_CUSTOMER = 'customer';

    public const TRANSITIONS = [
        DiableroTask::PENDING => [DiableroTask::AVAILABLE, DiableroTask::CANCELED, DiableroTask::FAILED],
        DiableroTask::AVAILABLE => [DiableroTask::ACCEPTED, DiableroTask::PENDING, DiableroTask::CANCELED, DiableroTask::FAILED],
        DiableroTask::ACCEPTED => [DiableroTask::ARRIVED, DiableroTask::AVAILABLE, DiableroTask::CANCELED, DiableroTask::FAILED],
        DiableroTask::ARRIVED => [DiableroTask::COLLECTED, DiableroTask::AVAILABLE, DiableroTask::CANCELED, DiableroTask::FAILED],
        DiableroTask::COLLECTED => [DiableroTask::IN_TRANSIT, DiableroTask::CANCELED, DiableroTask::FAILED],
        DiableroTask::IN_TRANSIT => [DiableroTask::HANDED_OVER, DiableroTask::CANCELED, DiableroTask::FAILED],
        DiableroTask::HANDED_OVER => [],
        DiableroTask::FAILED => [],
        DiableroTask::CANCELED => [],
    ];

    private const TIMESTAMP_COLUMNS = [
        DiableroTask::AVAILABLE => 'available_at',
        DiableroTask::ACCEPTED => 'accepted_at',
        DiableroTask::ARRIVED => 'arrived_at',
        DiableroTask::COLLECTED => 'collected_at',
        DiableroTask::IN_TRANSIT => 'in_transit_at',
        DiableroTask::HANDED_OVER => 'handed_over_at',
        DiableroTask::FAILED => 'failed_at',
        DiableroTask::CANCELED => 'canceled_at',
    ];

    public static function settings(): array
    {
        $values = BusinessSetting::whereIn('key', ['diablero_service_status', 'diablero_fee', 'diablero_fee_name', 'diablero_commission'])
            ->pluck('value', 'key');

        return [
            'status' => (int) ($values['diablero_service_status'] ?? 0),
            'fee' => round((float) ($values['diablero_fee'] ?? 0), 2),
            'fee_name' => $values['diablero_fee_name'] ?? 'Diablero Fee',
            'commission' => min(100, max(0, (float) ($values['diablero_commission'] ?? 0))),
        ];
    }

    public static function isEnabledForZone(Zone|int|null $zone): bool
    {
        if (!self::settings()['status']) {
            return false;
        }
        if (!$zone instanceof Zone) {
            $zone = $zone ? Zone::find($zone) : null;
        }
        if (!$zone || !$zone->diablero_status) {
            return false;
        }
        return (bool) LoadingZone::defaultForZone($zone->id);
    }

    public static function generateCode(): string
    {
        return (string) random_int(1000, 9999);
    }

    /**
     * Splits an amount over n parts in cents, putting the remainder on the first part.
     */
    public static function splitAmount(float $amount, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }
        $cents = (int) round($amount * 100);
        $base = intdiv($cents, $parts);
        $remainder = $cents - ($base * $parts);
        $result = array_fill(0, $parts, $base / 100);
        $result[0] = ($base + $remainder) / 100;
        return $result;
    }

    /**
     * Runs inside PlaceNewOrder before tips/charges are added, so wallet, COD limit and cashback
     * checks all see the final amount. $ctx comes from PlaceGroupOrder.
     */
    public static function applyGroupAdjustments(Order $order, array $ctx): void
    {
        $deliveryShare = round((float) ($ctx['delivery_share'] ?? 0), 2);
        $feeShare = round((float) ($ctx['diablero_fee_share'] ?? 0), 2);

        $oldDelivery = (float) $order->delivery_charge;
        $newDelivery = $order->free_delivery_by ? 0 : $deliveryShare;

        $order->original_delivery_charge = $deliveryShare;
        $order->delivery_charge = $newDelivery;
        $order->diablero_fee = $feeShare;
        $order->order_amount = round($order->order_amount - $oldDelivery + $newDelivery + $feeShare, 2);
        $order->order_group_id = $ctx['group_id'];
        $order->otp = $ctx['otp'];
        $order->distance = $ctx['distance'] ?? $order->distance;
        $order->dm_vehicle_id = $ctx['vehicle_id'] ?? $order->dm_vehicle_id;
        if (empty($ctx['charge_additional'])) {
            $order->additional_charge = 0;
        }
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 3);
    }

    public static function createTaskForGroup(OrderGroup $group): DiableroTask
    {
        $settings = self::settings();
        $fee = (float) $group->diablero_fee;
        $earning = round($fee - ($fee * $settings['commission'] / 100), 2);

        $task = DiableroTask::create([
            'order_group_id' => $group->id,
            'zone_id' => $group->zone_id,
            'loading_zone_id' => $group->loading_zone_id,
            'status' => DiableroTask::PENDING,
            'handover_code' => self::generateCode(),
            'diablero_fee' => $fee,
            'earning' => $earning,
        ]);

        $orders = Order::withoutGlobalScope(ZoneScope::class)->where('order_group_id', $group->id)->orderBy('id')->get();
        foreach ($orders as $index => $order) {
            DiableroTaskStop::create([
                'diablero_task_id' => $task->id,
                'order_id' => $order->id,
                'store_id' => $order->store_id,
                'sequence' => $index + 1,
                'status' => DiableroTaskStop::PENDING,
            ]);
        }

        self::log($task, null, DiableroTask::PENDING, self::ACTOR_SYSTEM, null, 'task_created');

        return $task;
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Compare-and-set transition so concurrent requests cannot both move the same task.
     */
    public static function transition(DiableroTask $task, string $to, string $actorType, ?int $actorId = null, ?string $note = null, array $extra = [], ?float $lat = null, ?float $lng = null): bool
    {
        $from = $task->status;
        if (!self::canTransition($from, $to)) {
            return false;
        }

        $data = array_merge($extra, ['status' => $to, 'updated_at' => now()]);
        if (isset(self::TIMESTAMP_COLUMNS[$to])) {
            $data[self::TIMESTAMP_COLUMNS[$to]] = now();
        }

        $affected = DiableroTask::where('id', $task->id)->where('status', $from)->update($data);
        if ($affected !== 1) {
            return false;
        }

        $task->refresh();
        self::log($task, $from, $to, $actorType, $actorId, $note, null, $lat, $lng);

        return true;
    }

    public static function log(DiableroTask $task, ?string $from, string $to, string $actorType, ?int $actorId = null, ?string $note = null, ?int $stopId = null, ?float $lat = null, ?float $lng = null): void
    {
        DiableroTaskLog::create([
            'diablero_task_id' => $task->id,
            'diablero_task_stop_id' => $stopId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note ? mb_substr($note, 0, 255) : null,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    public static function taskForOrder(Order $order): ?DiableroTask
    {
        if (!$order->order_group_id) {
            return null;
        }
        return DiableroTask::where('order_group_id', $order->order_group_id)->first();
    }

    /**
     * Single entry point called after any order status change outside this class.
     */
    public static function syncOrder(Order $order, string $actorType = self::ACTOR_SYSTEM, ?int $actorId = null): void
    {
        if (!$order->order_group_id) {
            return;
        }
        try {
            if (in_array($order->order_status, ['canceled', 'failed', 'refunded'])) {
                self::onOrderCanceled($order, $actorType, $actorId);
            } elseif ($order->order_status === 'handover') {
                self::refreshAvailability($order->order_group_id);
            }
        } catch (\Throwable $e) {
            info('DiableroLogic::syncOrder ' . $e->getMessage());
        }
    }

    /**
     * Grouped orders reach picked_up only through the deliveryman handover. Returns an error message
     * when $status is not allowed for $actorType, null otherwise.
     */
    public static function blockedStatusChange(Order $order, string $status, string $actorType): ?string
    {
        if (!$order->order_group_id || !in_array($status, ['picked_up', 'delivered'], true)) {
            return null;
        }
        $task = self::taskForOrder($order);
        if (!$task) {
            return null;
        }
        if ($actorType === self::ACTOR_VENDOR) {
            return translate('messages.this_order_is_collected_by_a_diablero');
        }
        if ($actorType === self::ACTOR_DELIVERYMAN && $status === 'picked_up') {
            return translate('messages.confirm_the_handover_code_from_the_diablero_to_pick_up_this_order');
        }
        if ($actorType === self::ACTOR_DELIVERYMAN && in_array($task->status, DiableroTask::OPEN_STATUSES, true)) {
            return translate('messages.this_order_has_not_been_handed_over_by_the_diablero_yet');
        }
        if ($actorType === self::ACTOR_ADMIN && in_array($task->status, DiableroTask::OPEN_STATUSES, true)) {
            return translate('messages.close_the_diablero_task_before_changing_this_status');
        }
        return null;
    }

    public static function activeStops(DiableroTask $task)
    {
        return $task->stops()->whereNotIn('status', [DiableroTaskStop::CANCELED, DiableroTaskStop::FAILED])->get();
    }

    public static function refreshAvailability(int $groupId): void
    {
        $task = DiableroTask::where('order_group_id', $groupId)->first();
        if (!$task || $task->status !== DiableroTask::PENDING) {
            return;
        }

        $stops = self::activeStops($task)->load('order');
        if ($stops->isEmpty()) {
            return;
        }
        $allReady = $stops->every(fn ($stop) => $stop->order && $stop->order->order_status === 'handover');
        if (!$allReady) {
            return;
        }

        if (self::transition($task, DiableroTask::AVAILABLE, self::ACTOR_SYSTEM, null, 'all_stores_ready')) {
            self::notifyZoneAvailable($task);
        }
    }

    public static function accept(DiableroTask $task, Diablero $diablero): array
    {
        if ((int) $diablero->active !== 1) {
            return [false, 'active_status', translate('messages.You_can_not_accept_task_while_offline')];
        }
        if (!$diablero->status || $diablero->application_status !== 'approved') {
            return [false, 'diablero', translate('messages.your_account_is_not_active')];
        }
        if ($task->zone_id && $diablero->zone_id && (int) $task->zone_id !== (int) $diablero->zone_id) {
            return [false, 'zone', translate('messages.task_not_in_your_zone')];
        }
        if (DiableroTaskRejection::where(['diablero_task_id' => $task->id, 'diablero_id' => $diablero->id])->exists()) {
            return [false, 'rejected', translate('messages.you_already_rejected_this_task')];
        }
        $ongoing = DiableroTask::where('diablero_id', $diablero->id)->whereIn('status', DiableroTask::ONGOING_STATUSES)->count();
        if ($ongoing >= self::MAX_ONGOING_TASKS) {
            return [false, 'max_tasks', translate('messages.diablero_maximum_task_exceed_warning')];
        }

        $affected = DiableroTask::where('id', $task->id)
            ->where('status', DiableroTask::AVAILABLE)
            ->whereNull('diablero_id')
            ->update([
                'diablero_id' => $diablero->id,
                'status' => DiableroTask::ACCEPTED,
                'accepted_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected !== 1) {
            return [false, 'already_accepted', translate('messages.task_already_accepted_by_another_diablero')];
        }

        $task->refresh();
        self::log($task, DiableroTask::AVAILABLE, DiableroTask::ACCEPTED, self::ACTOR_DIABLERO, $diablero->id);

        return [true, null, translate('messages.task_accepted_successfully')];
    }

    public static function reject(DiableroTask $task, Diablero $diablero): array
    {
        if ($task->diablero_id && (int) $task->diablero_id === (int) $diablero->id) {
            return [false, 'assigned', translate('messages.you_can_not_reject_an_accepted_task')];
        }
        DiableroTaskRejection::firstOrCreate(['diablero_task_id' => $task->id, 'diablero_id' => $diablero->id]);
        return [true, null, translate('messages.task_rejected')];
    }

    public static function updateStatusByDiablero(DiableroTask $task, Diablero $diablero, string $to, ?float $lat = null, ?float $lng = null): array
    {
        if ((int) $task->diablero_id !== (int) $diablero->id) {
            return [false, 'not_found', translate('messages.task_not_found')];
        }
        if (!in_array($to, [DiableroTask::ARRIVED, DiableroTask::IN_TRANSIT], true)) {
            return [false, 'status', translate('messages.invalid_status')];
        }
        if (!self::transition($task, $to, self::ACTOR_DIABLERO, $diablero->id, null, [], $lat, $lng)) {
            return [false, 'status', translate('messages.invalid_status_transition')];
        }
        if ($to === DiableroTask::IN_TRANSIT) {
            self::notifyDeliveryMan($task, translate('messages.diablero_is_on_the_way_to_loading_zone'));
        }
        return [true, null, translate('messages.status_updated')];
    }

    private static function assertCanWorkOnStops(DiableroTask $task, Diablero $diablero, DiableroTaskStop $stop): ?array
    {
        if ((int) $task->diablero_id !== (int) $diablero->id || (int) $stop->diablero_task_id !== (int) $task->id) {
            return [false, 'not_found', translate('messages.task_not_found')];
        }
        if ($task->status === DiableroTask::ACCEPTED) {
            self::transition($task, DiableroTask::ARRIVED, self::ACTOR_DIABLERO, $diablero->id, 'auto_arrived_on_first_stop');
        }
        if ($task->status !== DiableroTask::ARRIVED) {
            return [false, 'status', translate('messages.stops_can_only_be_updated_after_arrival')];
        }
        if ($stop->status !== DiableroTaskStop::PENDING) {
            return [false, 'stop', translate('messages.stop_already_processed')];
        }
        return null;
    }

    public static function collectStop(DiableroTask $task, DiableroTaskStop $stop, Diablero $diablero, ?float $lat = null, ?float $lng = null): array
    {
        if ($error = self::assertCanWorkOnStops($task, $diablero, $stop)) {
            return $error;
        }
        if ($stop->order && $stop->order->order_status !== 'handover') {
            return [false, 'order', translate('messages.order_is_not_ready_for_pickup')];
        }

        $affected = DiableroTaskStop::where('id', $stop->id)->where('status', DiableroTaskStop::PENDING)
            ->update(['status' => DiableroTaskStop::COLLECTED, 'collected_at' => now(), 'updated_at' => now()]);
        if ($affected !== 1) {
            return [false, 'stop', translate('messages.stop_already_processed')];
        }
        self::log($task, DiableroTaskStop::PENDING, 'stop_' . DiableroTaskStop::COLLECTED, self::ACTOR_DIABLERO, $diablero->id, null, $stop->id, $lat, $lng);

        self::evaluateStops($task->fresh(), $diablero->id);

        return [true, null, translate('messages.stop_collected')];
    }

    public static function failStop(DiableroTask $task, DiableroTaskStop $stop, Diablero $diablero, string $reason, ?float $lat = null, ?float $lng = null): array
    {
        if ($error = self::assertCanWorkOnStops($task, $diablero, $stop)) {
            return $error;
        }

        $affected = DiableroTaskStop::where('id', $stop->id)->where('status', DiableroTaskStop::PENDING)
            ->update(['status' => DiableroTaskStop::FAILED, 'failure_reason' => mb_substr($reason, 0, 255), 'failed_at' => now(), 'updated_at' => now()]);
        if ($affected !== 1) {
            return [false, 'stop', translate('messages.stop_already_processed')];
        }
        self::log($task, DiableroTaskStop::PENDING, 'stop_' . DiableroTaskStop::FAILED, self::ACTOR_DIABLERO, $diablero->id, $reason, $stop->id, $lat, $lng);

        $order = Order::withoutGlobalScope(ZoneScope::class)->find($stop->order_id);
        if ($order) {
            self::cancelOrder($order, 'diablero_pickup_failed: ' . $reason, 'admin');
        }
        self::notifyAdmin(translate('messages.diablero_pickup_failed') . ' #' . $stop->order_id, $task);

        self::evaluateStops($task->fresh(), $diablero->id);

        return [true, null, translate('messages.stop_marked_failed')];
    }

    /**
     * Moves the task forward once every stop has been resolved.
     */
    public static function evaluateStops(DiableroTask $task, ?int $actorId = null): void
    {
        $stops = $task->stops()->get();
        if ($stops->contains('status', DiableroTaskStop::PENDING)) {
            return;
        }
        $collected = $stops->where('status', DiableroTaskStop::COLLECTED)->count();

        if ($collected === 0) {
            self::transition($task, DiableroTask::FAILED, self::ACTOR_SYSTEM, $actorId, 'all_stops_failed_or_canceled');
            return;
        }
        if ($task->status === DiableroTask::ARRIVED) {
            self::transition($task, DiableroTask::COLLECTED, self::ACTOR_DIABLERO, $actorId, 'all_stops_collected');
        }
    }

    public static function confirmHandover(OrderGroup $group, DeliveryMan $dm, string $code): array
    {
        $task = DiableroTask::where('order_group_id', $group->id)->first();
        if (!$task) {
            return [false, 'not_found', translate('messages.task_not_found'), 404];
        }

        $orders = Order::withoutGlobalScope(ZoneScope::class)->where('order_group_id', $group->id)
            ->whereNotIn('order_status', ['canceled', 'failed', 'refunded', 'refund_requested'])->get();
        if ($orders->isEmpty() || $orders->contains(fn ($order) => (int) $order->delivery_man_id !== (int) $dm->id)) {
            return [false, 'not_assigned', translate('messages.this_order_is_not_assigned_to_you'), 403];
        }
        if ($task->status !== DiableroTask::IN_TRANSIT) {
            return [false, 'status', translate('messages.diablero_has_not_started_transit_yet'), 403];
        }
        if (!hash_equals((string) $task->handover_code, trim($code))) {
            return [false, 'code', translate('messages.handover_code_not_matched'), 406];
        }

        $collectedOrderIds = $task->stops()->where('status', DiableroTaskStop::COLLECTED)->pluck('order_id')->all();

        DB::beginTransaction();
        try {
            if (!self::transition($task, DiableroTask::HANDED_OVER, self::ACTOR_DELIVERYMAN, $dm->id, null, ['delivery_man_id' => $dm->id])) {
                DB::rollBack();
                return [false, 'status', translate('messages.invalid_status_transition'), 409];
            }
            foreach ($orders as $order) {
                if (!in_array($order->id, $collectedOrderIds)) {
                    continue;
                }
                $order->order_status = 'picked_up';
                $order->picked_up = now();
                $order->save();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            info('DiableroLogic::confirmHandover ' . $e->getMessage());
            return [false, 'error', translate('messages.failed_to_update'), 500];
        }

        foreach ($orders as $order) {
            if (in_array($order->id, $collectedOrderIds)) {
                try {
                    Helpers::send_order_notification($order);
                } catch (\Throwable $e) {
                    info($e->getMessage());
                }
            }
        }
        $first = $orders->firstWhere('id', $collectedOrderIds[0] ?? null);
        if ($first) {
            try {
                Helpers::sendOrderDeliveryVerificationOtp($first);
            } catch (\Throwable $e) {
                info($e->getMessage());
            }
        }

        if ($task->diablero) {
            self::notifyDiablero($task->diablero, translate('messages.handover_confirmed'), translate('messages.deliveryman_confirmed_receipt_of_task') . ' #' . $task->id, $task);
        }

        return [true, null, translate('messages.handover_confirmed'), 200];
    }

    public static function onOrderCanceled(Order $order, string $actorType = self::ACTOR_SYSTEM, ?int $actorId = null): void
    {
        $task = self::taskForOrder($order);
        if (!$task || in_array($task->status, DiableroTask::CLOSED_STATUSES)) {
            return;
        }

        $stop = $task->stops()->where('order_id', $order->id)->first();
        if ($stop && $stop->status === DiableroTaskStop::PENDING) {
            $stop->update(['status' => DiableroTaskStop::CANCELED]);
            self::log($task, DiableroTaskStop::PENDING, 'stop_' . DiableroTaskStop::CANCELED, $actorType, $actorId, 'order_canceled', $stop->id);
        }

        $remaining = self::activeStops($task)->count();
        if ($remaining === 0) {
            $stops = $task->stops()->get();
            $anyFailed = $stops->contains('status', DiableroTaskStop::FAILED);
            $anyCollected = $stops->contains('status', DiableroTaskStop::COLLECTED);
            if ($anyCollected) {
                self::evaluateStops($task->fresh(), $actorId);
            } elseif ($anyFailed) {
                self::transition($task, DiableroTask::FAILED, $actorType, $actorId, 'all_stops_failed_or_canceled');
            } else {
                self::cancelTask($task, 'all_orders_canceled', $actorType, $actorId);
            }
            return;
        }

        if ($task->status === DiableroTask::PENDING) {
            self::refreshAvailability($task->order_group_id);
        } elseif ($task->status === DiableroTask::ARRIVED) {
            self::evaluateStops($task->fresh(), $actorId);
        }

        if ($task->diablero) {
            self::notifyDiablero($task->diablero, translate('messages.task_updated'), translate('messages.an_order_in_your_task_was_canceled') . ' #' . $order->id, $task);
        }
    }

    public static function cancelTask(DiableroTask $task, string $reason, string $actorType, ?int $actorId = null): bool
    {
        $diablero = $task->diablero;
        if (!self::transition($task, DiableroTask::CANCELED, $actorType, $actorId, $reason, ['cancel_reason' => mb_substr($reason, 0, 255)])) {
            return false;
        }
        $task->stops()->where('status', DiableroTaskStop::PENDING)->update(['status' => DiableroTaskStop::CANCELED]);
        if ($diablero) {
            self::notifyDiablero($diablero, translate('messages.task_canceled'), translate('messages.your_task_has_been_canceled') . ' #' . $task->id, $task);
        }
        return true;
    }

    public static function unassign(DiableroTask $task, string $actorType, ?int $actorId = null): bool
    {
        if (!in_array($task->status, [DiableroTask::ACCEPTED, DiableroTask::ARRIVED], true)) {
            return false;
        }
        if ($task->stops()->where('status', DiableroTaskStop::COLLECTED)->exists()) {
            return false;
        }
        $diablero = $task->diablero;
        if (!self::transition($task, DiableroTask::AVAILABLE, $actorType, $actorId, 'unassigned', ['diablero_id' => null, 'accepted_at' => null, 'arrived_at' => null])) {
            return false;
        }
        if ($diablero) {
            DiableroTaskRejection::firstOrCreate(['diablero_task_id' => $task->id, 'diablero_id' => $diablero->id]);
            self::notifyDiablero($diablero, translate('messages.task_unassigned'), translate('messages.you_are_unassigned_from_a_task') . ' #' . $task->id, $task);
        }
        self::notifyZoneAvailable($task);
        return true;
    }

    public static function assign(DiableroTask $task, Diablero $diablero, int $adminId): array
    {
        if ($task->status !== DiableroTask::AVAILABLE || $task->diablero_id) {
            return [false, translate('messages.task_is_not_available')];
        }
        $affected = DiableroTask::where('id', $task->id)->where('status', DiableroTask::AVAILABLE)->whereNull('diablero_id')
            ->update(['diablero_id' => $diablero->id, 'status' => DiableroTask::ACCEPTED, 'accepted_at' => now(), 'updated_at' => now()]);
        if ($affected !== 1) {
            return [false, translate('messages.task_already_accepted_by_another_diablero')];
        }
        $task->refresh();
        self::log($task, DiableroTask::AVAILABLE, DiableroTask::ACCEPTED, self::ACTOR_ADMIN, $adminId, 'assigned_by_admin');
        self::notifyDiablero($diablero, translate('messages.new_task_assigned'), translate('messages.a_task_has_been_assigned_to_you') . ' #' . $task->id, $task);
        return [true, translate('messages.task_assigned')];
    }

    /**
     * Mirrors the admin cancel flow: restores stock, refunds paid amounts and notifies the customer.
     */
    public static function cancelOrder(Order $order, string $reason, string $canceledBy = 'admin'): bool
    {
        if (in_array($order->order_status, ['delivered', 'canceled', 'refund_requested', 'refunded', 'failed'])) {
            return false;
        }

        try {
            $order->cancellation_reason = mb_substr($reason, 0, 255);
            $order->canceled_by = $canceledBy;

            if ($order->store) {
                Helpers::increment_order_count($order->store);
            }

            if ($order->module && (config('module.' . $order->module->module_type)['stock'] ?? false)) {
                foreach ($order->details as $detail) {
                    $variant = json_decode($detail['variation'], true) ?? [];
                    $item = $detail->campaign ?: $detail->item;
                    if ($item) {
                        ProductLogic::update_stock($item, -$detail->quantity, count($variant) ? $variant[0]['type'] : null)->save();
                    }
                }
            }

            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                $dm->save();
            }

            if ($order->is_guest == 0) {
                OrderLogic::refund_before_delivered($order);
            }

            $order->order_status = 'canceled';
            $order->canceled = now();
            $order->save();

            Helpers::send_order_notification($order);
            $actorType = match ($canceledBy) {
                'admin' => self::ACTOR_ADMIN,
                'customer' => self::ACTOR_CUSTOMER,
                'vendor', 'store' => self::ACTOR_VENDOR,
                default => self::ACTOR_SYSTEM,
            };
            self::syncOrder($order->fresh(), $actorType);
        } catch (\Throwable $e) {
            info('DiableroLogic::cancelOrder ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Called from OrderLogic::create_transaction. Returns the diablero split for one order and credits
     * the diablero wallet once per order.
     */
    public static function settleOrder(Order $order): array
    {
        $fee = (float) ($order->diablero_fee ?? 0);
        $result = ['fee' => $fee, 'commission' => 0.0, 'earning' => 0.0, 'diablero_id' => null];
        if ($fee <= 0 || !$order->order_group_id) {
            $result['commission'] = $fee;
            return $result;
        }

        $task = self::taskForOrder($order);
        if (!$task || !$task->diablero_id || $task->status !== DiableroTask::HANDED_OVER || $task->diablero_fee <= 0) {
            $result['commission'] = $fee;
            return $result;
        }

        $earning = round($fee * ($task->earning / $task->diablero_fee), 2);
        $result['earning'] = $earning;
        $result['commission'] = round($fee - $earning, 2);
        $result['diablero_id'] = $task->diablero_id;

        return $result;
    }

    public static function creditDiablero(Order $order, array $split): void
    {
        if (empty($split['diablero_id']) || $split['earning'] <= 0) {
            return;
        }
        DiableroWallet::firstOrCreate(['diablero_id' => $split['diablero_id']]);
        $taskId = self::taskForOrder($order)?->id;
        try {
            DB::transaction(function () use ($order, $split, $taskId) {
                $wallet = DiableroWallet::where('diablero_id', $split['diablero_id'])->lockForUpdate()->first();
                if (DiableroWalletTransaction::where(['order_id' => $order->id, 'transaction_type' => 'task_earning'])->exists()) {
                    return;
                }
                $wallet->total_earning = $wallet->total_earning + $split['earning'];
                $wallet->save();

                DiableroWalletTransaction::create([
                    'diablero_id' => $split['diablero_id'],
                    'diablero_task_id' => $taskId,
                    'order_id' => $order->id,
                    'transaction_type' => 'task_earning',
                    'credit' => $split['earning'],
                    'debit' => 0,
                    'balance' => $wallet->balance,
                    'reference' => 'order#' . $order->id,
                ]);
            });
        } catch (\Throwable $e) {
            info('DiableroLogic::creditDiablero ' . $e->getMessage());
        }
    }

    public static function recordPayout(Diablero $diablero, float $amount, ?string $reference, ?string $note): array
    {
        DiableroWallet::firstOrCreate(['diablero_id' => $diablero->id]);
        $recorded = DB::transaction(function () use ($diablero, $amount, $reference, $note) {
            $wallet = DiableroWallet::where('diablero_id', $diablero->id)->lockForUpdate()->first();
            if ($amount <= 0 || round($amount, 2) > round($wallet->balance, 2)) {
                return false;
            }
            $wallet->total_withdrawn = $wallet->total_withdrawn + $amount;
            $wallet->save();

            DiableroWalletTransaction::create([
                'diablero_id' => $diablero->id,
                'transaction_type' => 'payout',
                'credit' => 0,
                'debit' => $amount,
                'balance' => $wallet->balance,
                'reference' => $reference,
                'note' => $note,
            ]);

            return true;
        });
        if (!$recorded) {
            return [false, translate('messages.insufficient_balance')];
        }

        self::notifyDiablero($diablero, translate('messages.payout_recorded'), translate('messages.a_payout_of') . ' ' . Helpers::format_currency($amount) . ' ' . translate('messages.has_been_recorded'), null, 'payout');

        return [true, translate('messages.payout_recorded')];
    }

    public static function notifyDiablero(Diablero $diablero, string $title, string $description, ?DiableroTask $task = null, string $type = 'diablero_task'): void
    {
        $data = [
            'title' => $title,
            'description' => $description,
            'order_id' => $task?->id ?? '',
            'data_id' => $task?->id ?? '',
            'status' => $task?->status ?? '',
            'image' => '',
            'type' => $type,
        ];
        try {
            if ($diablero->fcm_token) {
                Helpers::send_push_notif_to_device($diablero->fcm_token, $data);
            }
            DB::table('user_notifications')->insert([
                'data' => json_encode($data),
                'diablero_id' => $diablero->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            info('DiableroLogic::notifyDiablero ' . $e->getMessage());
        }
    }

    public static function zoneTopic(?int $zoneId): string
    {
        return 'diablero_zone_' . ($zoneId ?? 0);
    }

    public static function notifyZoneAvailable(DiableroTask $task): void
    {
        $data = [
            'title' => translate('messages.new_pickup_task'),
            'description' => translate('messages.a_new_pickup_task_is_available') . ' #' . $task->id,
            'order_id' => $task->id,
            'zone_id' => $task->zone_id,
            'image' => '',
        ];
        try {
            Helpers::send_push_notif_to_topic($data, self::zoneTopic($task->zone_id), 'diablero_task_request');
        } catch (\Throwable $e) {
            info('DiableroLogic::notifyZoneAvailable ' . $e->getMessage());
        }
    }

    public static function notifyDeliveryMan(DiableroTask $task, string $description): void
    {
        $order = Order::withoutGlobalScope(ZoneScope::class)->where('order_group_id', $task->order_group_id)
            ->whereNotNull('delivery_man_id')->first();
        $dm = $order?->delivery_man;
        if (!$dm || !$dm->fcm_token) {
            return;
        }
        try {
            Helpers::send_push_notif_to_device($dm->fcm_token, [
                'title' => translate('messages.diablero_update'),
                'description' => $description,
                'order_id' => $order->id,
                'image' => '',
                'type' => 'order_status',
            ]);
        } catch (\Throwable $e) {
            info('DiableroLogic::notifyDeliveryMan ' . $e->getMessage());
        }
    }

    public static function notifyAdmin(string $description, DiableroTask $task): void
    {
        try {
            Helpers::send_push_notif_to_topic([
                'title' => translate('messages.diablero_alert'),
                'description' => $description,
                'order_id' => $task->id,
                'image' => '',
            ], 'admin_message', 'diablero_alert');
        } catch (\Throwable $e) {
            info('DiableroLogic::notifyAdmin ' . $e->getMessage());
        }
    }

    public static function formatTask(DiableroTask $task, ?Diablero $viewer = null, bool $detailed = true): array
    {
        $task->loadMissing(['loadingZone', 'stops.order.details', 'stops.store', 'group']);

        $isOwner = $viewer && (int) $task->diablero_id === (int) $viewer->id;
        $stops = $task->stops->map(function (DiableroTaskStop $stop) use ($detailed) {
            $store = $stop->store;
            $row = [
                'id' => $stop->id,
                'sequence' => $stop->sequence,
                'status' => $stop->status,
                'order_id' => $stop->order_id,
                'order_status' => $stop->order?->order_status,
                'failure_reason' => $stop->failure_reason,
                'collected_at' => $stop->collected_at?->toDateTimeString(),
                'store' => $store ? [
                    'id' => $store->id,
                    'name' => $store->name,
                    'phone' => $store->phone,
                    'address' => $store->address,
                    'latitude' => (string) $store->latitude,
                    'longitude' => (string) $store->longitude,
                    'logo_full_url' => $store->logo_full_url ?? null,
                ] : null,
                'items_count' => (int) ($stop->order?->details?->sum('quantity') ?? 0),
            ];
            if ($detailed) {
                $row['items'] = ($stop->order?->details ?? collect())->map(function ($detail) {
                    $info = json_decode($detail->item_details, true) ?? [];
                    $variation = json_decode($detail->variation, true) ?? [];
                    return [
                        'id' => $detail->id,
                        'item_id' => $detail->item_id,
                        'name' => $info['name'] ?? '',
                        'image_full_url' => $info['image_full_url'] ?? null,
                        'quantity' => (int) $detail->quantity,
                        'variation' => collect($variation)->pluck('type')->filter()->implode(', '),
                    ];
                })->values();
            }
            return $row;
        })->values();

        $loadingZone = $task->loadingZone;
        $data = [
            'id' => $task->id,
            'order_group_id' => $task->order_group_id,
            'status' => $task->status,
            'zone_id' => $task->zone_id,
            'earning' => (float) $task->earning,
            'stops_count' => $stops->count(),
            'items_count' => (int) $stops->sum('items_count'),
            'loading_zone' => $loadingZone ? [
                'id' => $loadingZone->id,
                'name' => $loadingZone->name,
                'address' => $loadingZone->address,
                'latitude' => (string) $loadingZone->latitude,
                'longitude' => (string) $loadingZone->longitude,
            ] : null,
            'stops' => $stops,
            'created_at' => $task->created_at?->toDateTimeString(),
            'available_at' => $task->available_at?->toDateTimeString(),
            'accepted_at' => $task->accepted_at?->toDateTimeString(),
            'arrived_at' => $task->arrived_at?->toDateTimeString(),
            'collected_at' => $task->collected_at?->toDateTimeString(),
            'in_transit_at' => $task->in_transit_at?->toDateTimeString(),
            'handed_over_at' => $task->handed_over_at?->toDateTimeString(),
            'canceled_at' => $task->canceled_at?->toDateTimeString(),
            'failed_at' => $task->failed_at?->toDateTimeString(),
            'cancel_reason' => $task->cancel_reason,
        ];

        if ($isOwner) {
            if (in_array($task->status, [DiableroTask::COLLECTED, DiableroTask::IN_TRANSIT], true)) {
                $data['handover_code'] = $task->handover_code;
            }
            $dmOrder = Order::withoutGlobalScope(ZoneScope::class)->where('order_group_id', $task->order_group_id)
                ->whereNotNull('delivery_man_id')->with('delivery_man')->first();
            $dm = $dmOrder?->delivery_man;
            $data['delivery_man'] = $dm ? [
                'id' => $dm->id,
                'name' => trim($dm->f_name . ' ' . $dm->l_name),
                'phone' => $dm->phone,
                'image_full_url' => $dm->image_full_url,
            ] : null;
        }

        return $data;
    }

    /**
     * Summary attached to order payloads for the deliveryman, vendor and customer apps.
     */
    public static function orderSummary(Order $order): ?array
    {
        if (!$order->order_group_id) {
            return null;
        }
        $task = self::taskForOrder($order);
        $group = $order->order_group ?? OrderGroup::find($order->order_group_id);
        $loadingZone = $task?->loadingZone ?? $group?->loadingZone;
        $diablero = $task?->diablero;

        $siblings = Order::withoutGlobalScope(ZoneScope::class)->with('store:id,name,phone,address,latitude,longitude')
            ->where('order_group_id', $order->order_group_id)->orderBy('id')->get(['id', 'store_id', 'order_status', 'order_amount']);

        return [
            'order_group_id' => $order->order_group_id,
            'task_id' => $task?->id,
            'task_status' => $task?->status,
            'loading_zone' => $loadingZone ? [
                'id' => $loadingZone->id,
                'name' => $loadingZone->name,
                'address' => $loadingZone->address,
                'latitude' => (string) $loadingZone->latitude,
                'longitude' => (string) $loadingZone->longitude,
            ] : null,
            'diablero' => $diablero ? [
                'id' => $diablero->id,
                'name' => $diablero->full_name,
                'phone' => $diablero->phone,
                'image_full_url' => $diablero->image_full_url,
            ] : null,
            'orders' => $siblings->map(fn ($o) => [
                'id' => $o->id,
                'order_status' => $o->order_status,
                'order_amount' => (float) $o->order_amount,
                'store_name' => $o->store?->name,
                'store_address' => $o->store?->address,
            ])->values(),
            'group_order_amount' => (float) $siblings->whereNotIn('order_status', ['canceled', 'failed', 'refunded'])->sum('order_amount'),
        ];
    }
}
