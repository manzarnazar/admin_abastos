<?php
/**
 * Reset all categories & products, then seed a clean grocery catalog
 * owned by vendor@gmail.com.
 *
 * Run: php reset_catalog.php
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$now = now();
$datePrefix = $now->format('Y-m-d');

function downloadImage(string $url, string $destPath): bool
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 30,
            'header' => "User-Agent: AbastosCatalogSeeder/1.0\r\nAccept: image/*\r\n",
            'follow_location' => 1,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 1000) {
        return false;
    }
    return file_put_contents($destPath, $data) !== false;
}

function makeFilename(string $prefix, string $ext = 'jpg'): string
{
    return $prefix . '-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 12) . '.' . $ext;
}

echo "==> Downloading category & product images...\n";

$categoryDefs = [
    ['name' => 'Frutas y Verduras', 'slug' => 'frutas-y-verduras', 'query' => 'fresh-vegetables-market'],
    ['name' => 'Carnes y Pollo', 'slug' => 'carnes-y-pollo', 'query' => 'fresh-meat-chicken'],
    ['name' => 'Lácteos', 'slug' => 'lacteos', 'query' => 'dairy-milk-cheese'],
    ['name' => 'Panadería', 'slug' => 'panaderia', 'query' => 'fresh-bread-bakery'],
    ['name' => 'Bebidas', 'slug' => 'bebidas', 'query' => 'fresh-juice-drinks'],
    ['name' => 'Abarrotes', 'slug' => 'abarrotes', 'query' => 'grocery-pantry-rice'],
    ['name' => 'Snacks', 'slug' => 'snacks', 'query' => 'healthy-snacks-chips'],
    ['name' => 'Limpieza', 'slug' => 'limpieza', 'query' => 'cleaning-supplies'],
    ['name' => 'Congelados', 'slug' => 'congelados', 'query' => 'frozen-food'],
    ['name' => 'Condimentos', 'slug' => 'condimentos', 'query' => 'spices-condiments'],
];

// Curated Unsplash image IDs (stable direct URLs) — grocery / food only
$categoryImages = [
    'frutas-y-verduras' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=600&h=600&fit=crop',
    'carnes-y-pollo' => 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=600&h=600&fit=crop',
    'lacteos' => 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?w=600&h=600&fit=crop',
    'panaderia' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&h=600&fit=crop',
    'bebidas' => 'https://images.unsplash.com/photo-1622597467836-f3285f2131b8?w=600&h=600&fit=crop',
    'abarrotes' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=600&h=600&fit=crop',
    'snacks' => 'https://images.unsplash.com/photo-1566478989037-ed2696b2ca90?w=600&h=600&fit=crop',
    'limpieza' => 'https://images.unsplash.com/photo-1563453392212-326f5e854473?w=600&h=600&fit=crop',
    'congelados' => 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=600&h=600&fit=crop',
    'condimentos' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=600&h=600&fit=crop',
];

$productDefs = [
    // Frutas y Verduras
    ['cat' => 'frutas-y-verduras', 'name' => 'Manzana Roja', 'desc' => 'Manzanas rojas frescas, dulces y crujientes.', 'price' => 45.00, 'stock' => 100, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=800&h=800&fit=crop'],
    ['cat' => 'frutas-y-verduras', 'name' => 'Plátano', 'desc' => 'Plátanos maduros ideales para consumo diario.', 'price' => 28.00, 'stock' => 120, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=800&h=800&fit=crop'],
    ['cat' => 'frutas-y-verduras', 'name' => 'Tomate Saladette', 'desc' => 'Tomates frescos para ensaladas y guisos.', 'price' => 32.00, 'stock' => 90, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1546470427-e212b7d31075?w=800&h=800&fit=crop'],
    // Carnes
    ['cat' => 'carnes-y-pollo', 'name' => 'Pechuga de Pollo', 'desc' => 'Pechuga de pollo fresca sin hueso.', 'price' => 120.00, 'stock' => 50, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=800&h=800&fit=crop'],
    ['cat' => 'carnes-y-pollo', 'name' => 'Carne Molida de Res', 'desc' => 'Carne molida magra para guisos y hamburguesas.', 'price' => 160.00, 'stock' => 40, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1603048297172-c92544798d5a?w=800&h=800&fit=crop'],
    // Lacteos
    ['cat' => 'lacteos', 'name' => 'Leche Entera 1L', 'desc' => 'Leche entera pasteurizada 1 litro.', 'price' => 28.00, 'stock' => 80, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=800&h=800&fit=crop'],
    ['cat' => 'lacteos', 'name' => 'Queso Panela', 'desc' => 'Queso panela fresco por kilogramo.', 'price' => 95.00, 'stock' => 35, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?w=800&h=800&fit=crop'],
    ['cat' => 'lacteos', 'name' => 'Yogurt Natural', 'desc' => 'Yogurt natural sin azúcar añadida.', 'price' => 35.00, 'stock' => 60, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=800&h=800&fit=crop'],
    // Panaderia
    ['cat' => 'panaderia', 'name' => 'Pan de Caja', 'desc' => 'Pan de caja suave, ideal para sándwiches.', 'price' => 42.00, 'stock' => 45, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=800&h=800&fit=crop'],
    ['cat' => 'panaderia', 'name' => 'Tortillas de Maíz', 'desc' => 'Paquete de tortillas de maíz recién hechas.', 'price' => 22.00, 'stock' => 100, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=800&h=800&fit=crop'],
    // Bebidas
    ['cat' => 'bebidas', 'name' => 'Agua Purificada 1.5L', 'desc' => 'Agua purificada embotellada 1.5 litros.', 'price' => 18.00, 'stock' => 150, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=800&h=800&fit=crop'],
    ['cat' => 'bebidas', 'name' => 'Jugo de Naranja', 'desc' => 'Jugo de naranja natural 1 litro.', 'price' => 38.00, 'stock' => 55, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=800&h=800&fit=crop'],
    // Abarrotes
    ['cat' => 'abarrotes', 'name' => 'Arroz 1kg', 'desc' => 'Arroz blanco de grano largo, 1 kilogramo.', 'price' => 32.00, 'stock' => 200, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=800&h=800&fit=crop'],
    ['cat' => 'abarrotes', 'name' => 'Frijol Negro 1kg', 'desc' => 'Frijol negro seco, 1 kilogramo.', 'price' => 40.00, 'stock' => 150, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1596797038530-2c107229654b?w=800&h=800&fit=crop'],
    ['cat' => 'abarrotes', 'name' => 'Aceite Vegetal 1L', 'desc' => 'Aceite vegetal comestible 1 litro.', 'price' => 48.00, 'stock' => 70, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1474979266404-7ea332dc577a?w=800&h=800&fit=crop'],
    // Snacks
    ['cat' => 'snacks', 'name' => 'Papas Fritas', 'desc' => 'Bolsa de papas fritas clásicas.', 'price' => 25.00, 'stock' => 80, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1566478989037-ed2696b2ca90?w=800&h=800&fit=crop'],
    ['cat' => 'snacks', 'name' => 'Galletas de Avena', 'desc' => 'Galletas de avena empaquetadas.', 'price' => 30.00, 'stock' => 65, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=800&h=800&fit=crop'],
    // Limpieza
    ['cat' => 'limpieza', 'name' => 'Detergente Líquido', 'desc' => 'Detergente líquido para ropa, 1 litro.', 'price' => 55.00, 'stock' => 40, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1610557892470-55d9e80c0bce?w=800&h=800&fit=crop'],
    ['cat' => 'limpieza', 'name' => 'Jabón de Manos', 'desc' => 'Jabón líquido antibacterial para manos.', 'price' => 35.00, 'stock' => 50, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1585127150898-0b4f0c0b0b0b?w=800&h=800&fit=crop'],
    // Congelados
    ['cat' => 'congelados', 'name' => 'Helado de Vainilla', 'desc' => 'Helado de vainilla 1 litro.', 'price' => 85.00, 'stock' => 30, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1563805042-7684c019e1cd?w=800&h=800&fit=crop'],
    ['cat' => 'congelados', 'name' => 'Verduras Mixtas Congeladas', 'desc' => 'Mezcla de verduras congeladas 500g.', 'price' => 45.00, 'stock' => 40, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1459411621453-7b03977f4bfc?w=800&h=800&fit=crop'],
    // Condimentos
    ['cat' => 'condimentos', 'name' => 'Sal de Mesa', 'desc' => 'Sal refinada de mesa 1kg.', 'price' => 15.00, 'stock' => 100, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1518110925495-5fe2cdf747a3?w=800&h=800&fit=crop'],
    ['cat' => 'condimentos', 'name' => 'Azúcar Estándar', 'desc' => 'Azúcar refinada 1kg.', 'price' => 28.00, 'stock' => 90, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1581441363689-1f3c3c414635?w=800&h=800&fit=crop'],
];

// Fix bad soap URL - use a reliable one
$productDefs[18]['img'] = 'https://images.unsplash.com/photo-1600857544200-b2f666a9a2ec?w=800&h=800&fit=crop';

$catDir = storage_path('app/public/category');
$prodDir = storage_path('app/public/product');
@mkdir($catDir, 0755, true);
@mkdir($prodDir, 0755, true);

$categoryFiles = [];
foreach ($categoryDefs as $cat) {
    $file = makeFilename($datePrefix, 'jpg');
    $ok = downloadImage($categoryImages[$cat['slug']], $catDir . '/' . $file);
    if (!$ok) {
        echo "  WARN: category image failed for {$cat['name']}, retrying fallback...\n";
        $ok = downloadImage('https://picsum.photos/seed/' . $cat['slug'] . '/600/600.jpg', $catDir . '/' . $file);
    }
    echo ($ok ? '  OK' : '  FAIL') . " category: {$cat['name']} -> {$file}\n";
    $categoryFiles[$cat['slug']] = $ok ? $file : 'def.png';
}

$productFiles = [];
foreach ($productDefs as $i => $p) {
    $file = makeFilename($datePrefix, 'jpg');
    $ok = downloadImage($p['img'], $prodDir . '/' . $file);
    if (!$ok) {
        echo "  WARN: product image failed for {$p['name']}, using picsum...\n";
        $ok = downloadImage('https://picsum.photos/seed/prod' . $i . '/800/800.jpg', $prodDir . '/' . $file);
    }
    echo ($ok ? '  OK' : '  FAIL') . " product: {$p['name']} -> {$file}\n";
    $productFiles[$i] = $ok ? $file : 'def.png';
}

echo "\n==> Resetting database catalog...\n";

DB::beginTransaction();
try {
    // Related cleanup
    $itemIds = DB::table('items')->pluck('id')->all();
    $catIds = DB::table('categories')->pluck('id')->all();

    if ($itemIds) {
        DB::table('order_details')->whereIn('item_id', $itemIds)->delete();
        if (DB::getSchemaBuilder()->hasTable('carts')) {
            DB::table('carts')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('wishlists')) {
            DB::table('wishlists')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('reviews')) {
            DB::table('reviews')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('item_tag')) {
            DB::table('item_tag')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('item_nutrition')) {
            DB::table('item_nutrition')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('item_generic_names')) {
            DB::table('item_generic_names')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('allergy_item')) {
            DB::table('allergy_item')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('ecommerce_item_details')) {
            DB::table('ecommerce_item_details')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('flash_sale_items')) {
            DB::table('flash_sale_items')->whereIn('item_id', $itemIds)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('pharmacy_item_details')) {
            DB::table('pharmacy_item_details')->whereIn('item_id', $itemIds)->delete();
        }
        DB::table('translations')->where('translationable_type', 'App\\Models\\Item')->whereIn('translationable_id', $itemIds)->delete();
        DB::table('storages')->where('data_type', 'App\\Models\\Item')->whereIn('data_id', $itemIds)->delete();
    }

    if ($catIds) {
        DB::table('translations')->where('translationable_type', 'App\\Models\\Category')->whereIn('translationable_id', $catIds)->delete();
        DB::table('storages')->where('data_type', 'App\\Models\\Category')->whereIn('data_id', $catIds)->delete();
    }

    DB::table('items')->delete();
    DB::table('categories')->delete();
    echo "  Deleted all items and categories.\n";

    // Units
    $unitMap = [];
    foreach (['kg' => 'kg', 'pc' => 'pc', 'lt' => 'lt'] as $u => $label) {
        $existing = DB::table('units')->where('unit', $label)->first();
        if ($existing) {
            $unitMap[$u] = $existing->id;
        } else {
            $unitMap[$u] = DB::table('units')->insertGetId([
                'unit' => $label,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // Enable grocery module
    DB::table('modules')->where('id', 1)->update(['status' => 1]);

    // Vendor + store
    $vendor = DB::table('vendors')->where('email', 'vendor@gmail.com')->first();
    if (!$vendor) {
        $vendorId = DB::table('vendors')->insertGetId([
            'f_name' => 'Vendor',
            'l_name' => 'Store',
            'phone' => '520000000001',
            'email' => 'vendor@gmail.com',
            'password' => Hash::make('12345678'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        echo "  Created vendor@gmail.com (password: 12345678)\n";
    } else {
        $vendorId = $vendor->id;
        DB::table('vendors')->where('id', $vendorId)->update([
            'status' => 1,
            'password' => Hash::make('12345678'),
            'updated_at' => $now,
        ]);
        echo "  Updated existing vendor@gmail.com\n";
    }

    $store = DB::table('stores')->where('vendor_id', $vendorId)->first();
    $zoneId = DB::table('zones')->where('id', 4)->exists() ? 4 : (DB::table('zones')->value('id') ?: 1);

    if (!$store) {
        $storeId = DB::table('stores')->insertGetId([
            'name' => 'Abastos Market',
            'phone' => '520000000001',
            'email' => 'vendor@gmail.com',
            'logo' => 'def.png',
            'cover_photo' => 'def.png',
            'latitude' => '20.5735',
            'longitude' => '-101.1950',
            'address' => 'Salamanca, Guanajuato, México',
            'minimum_order' => 0,
            'comission' => 10,
            'schedule_order' => 0,
            'status' => 1,
            'vendor_id' => $vendorId,
            'free_delivery' => 0,
            'delivery' => 1,
            'take_away' => 1,
            'zone_id' => $zoneId,
            'active' => 1,
            'module_id' => 1,
            'delivery_time' => '30-45',
            'veg' => 1,
            'non_veg' => 1,
            'store_business_model' => 'commission',
            'slug' => 'abastos-market',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        echo "  Created store Abastos Market (id={$storeId})\n";
    } else {
        $storeId = $store->id;
        DB::table('stores')->where('id', $storeId)->update([
            'status' => 1,
            'active' => 1,
            'module_id' => 1,
            'zone_id' => $zoneId,
            'updated_at' => $now,
        ]);
        echo "  Using existing store id={$storeId}\n";
    }

    // Ensure store has open schedule (all days)
    if (DB::getSchemaBuilder()->hasTable('store_schedule')) {
        DB::table('store_schedule')->where('store_id', $storeId)->delete();
        for ($d = 0; $d <= 6; $d++) {
            DB::table('store_schedule')->insert([
                'store_id' => $storeId,
                'day' => $d,
                'opening_time' => '00:00:00',
                'closing_time' => '23:59:59',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // Insert categories
    $catIdBySlug = [];
    $priority = 1;
    foreach ($categoryDefs as $cat) {
        $image = $categoryFiles[$cat['slug']];
        $id = DB::table('categories')->insertGetId([
            'name' => $cat['name'],
            'image' => $image,
            'parent_id' => 0,
            'position' => 0,
            'status' => 1,
            'priority' => $priority++,
            'module_id' => 1,
            'slug' => $cat['slug'],
            'featured' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $catIdBySlug[$cat['slug']] = $id;

        DB::table('storages')->updateOrInsert(
            ['data_type' => 'App\\Models\\Category', 'data_id' => (string) $id, 'key' => 'image'],
            ['value' => 'public', 'created_at' => $now, 'updated_at' => $now]
        );
        DB::table('translations')->insert([
            'translationable_type' => 'App\\Models\\Category',
            'translationable_id' => $id,
            'locale' => 'en',
            'key' => 'name',
            'value' => $cat['name'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        echo "  Category: {$cat['name']} (#{$id})\n";
    }

    // Insert products
    foreach ($productDefs as $i => $p) {
        $catId = $catIdBySlug[$p['cat']];
        $image = $productFiles[$i];
        $slugBase = Str::slug($p['name']);
        $itemId = DB::table('items')->insertGetId([
            'name' => $p['name'],
            'description' => $p['desc'],
            'image' => $image,
            'category_id' => $catId,
            'category_ids' => json_encode([['id' => (string) $catId, 'position' => 1]]),
            'variations' => json_encode([]),
            'add_ons' => json_encode([]),
            'attributes' => json_encode([]),
            'choice_options' => json_encode([]),
            'price' => $p['price'],
            'tax' => 0,
            'tax_type' => 'percent',
            'discount' => 0,
            'discount_type' => 'percent',
            'available_time_starts' => '00:00:00',
            'available_time_ends' => '23:59:59',
            'veg' => 1,
            'status' => 1,
            'store_id' => $storeId,
            'order_count' => 0,
            'avg_rating' => 0,
            'rating_count' => 0,
            'rating' => null,
            'module_id' => 1,
            'stock' => $p['stock'],
            'unit_id' => $unitMap[$p['unit']] ?? $unitMap['pc'],
            'images' => json_encode([]),
            'food_variations' => null,
            'slug' => $slugBase,
            'recommended' => 1,
            'organic' => 0,
            'maximum_cart_quantity' => 20,
            'is_approved' => 1,
            'is_halal' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Fix slug with id
        DB::table('items')->where('id', $itemId)->update(['slug' => $slugBase . $itemId]);

        DB::table('storages')->updateOrInsert(
            ['data_type' => 'App\\Models\\Item', 'data_id' => (string) $itemId, 'key' => 'image'],
            ['value' => 'public', 'created_at' => $now, 'updated_at' => $now]
        );
        DB::table('translations')->insert([
            [
                'translationable_type' => 'App\\Models\\Item',
                'translationable_id' => $itemId,
                'locale' => 'en',
                'key' => 'name',
                'value' => $p['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'translationable_type' => 'App\\Models\\Item',
                'translationable_id' => $itemId,
                'locale' => 'en',
                'key' => 'description',
                'value' => $p['desc'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        echo "  Product: {$p['name']} (#{$itemId}) \${$p['price']}\n";
    }

    DB::commit();
    echo "\n==> Done.\n";
    echo "Categories: " . DB::table('categories')->count() . "\n";
    echo "Products:   " . DB::table('items')->count() . "\n";
    echo "Vendor:     vendor@gmail.com / 12345678\n";
    echo "Store ID:   {$storeId}\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
