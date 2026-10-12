<?php
/**
 * Abastos production: add categories, refresh images, assign products to Manzar Nazar.
 *
 * Run on server: php update_manzar_catalog.php
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$now = now();
$datePrefix = $now->format('Y-m-d');
$MODULE_ID = 1;

function downloadImage(string $url, string $destPath): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'AbastosCatalogSeeder/2.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($code < 200 || $code >= 300 || !$data || strlen($data) < 1500) {
        echo "  FAIL download HTTP {$code} size=" . strlen((string) $data) . " {$url}\n";
        return false;
    }
    if ($ctype && stripos($ctype, 'image/') === false && stripos($ctype, 'octet-stream') === false) {
        echo "  FAIL content-type {$ctype} {$url}\n";
        return false;
    }
    if (!is_dir(dirname($destPath))) {
        mkdir(dirname($destPath), 0755, true);
    }
    file_put_contents($destPath, $data);
    @chmod($destPath, 0664);
    return is_file($destPath) && filesize($destPath) > 1500;
}

function makeFilename(string $prefix, string $ext = 'jpg'): string
{
    return $prefix . '-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 12) . '.' . $ext;
}

function uniqueSlug(string $name, string $table, ?int $ignoreId = null): string
{
    $slug = Str::slug($name);
    $base = $slug;
    $i = 2;
    while (true) {
        $q = DB::table($table)->where('slug', $slug);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if (!$q->exists()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function ensureUnit(string $label): int
{
    $existing = DB::table('units')->where('unit', $label)->first();
    if ($existing) {
        return (int) $existing->id;
    }
    return (int) DB::table('units')->insertGetId([
        'unit' => $label,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function upsertTranslation(string $type, int $id, string $key, string $value): void
{
    $row = DB::table('translations')->where([
        'translationable_type' => $type,
        'translationable_id' => $id,
        'locale' => 'en',
        'key' => $key,
    ])->first();
    if ($row) {
        DB::table('translations')->where('id', $row->id)->update([
            'value' => $value,
            'updated_at' => now(),
        ]);
        return;
    }
    DB::table('translations')->insert([
        'translationable_type' => $type,
        'translationable_id' => $id,
        'locale' => 'en',
        'key' => $key,
        'value' => $value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ensureStorage(string $type, int $id, string $key = 'image', string $value = 'public'): void
{
    DB::table('storages')->updateOrInsert(
        ['data_type' => $type, 'data_id' => (string) $id, 'key' => $key],
        ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
    );
}

$catDir = storage_path('app/public/category');
$prodDir = storage_path('app/public/product');
@mkdir($catDir, 0755, true);
@mkdir($prodDir, 0755, true);

// --- Seller: Manzar Nazar ---
$vendor = DB::table('vendors')->where('email', 'manzarnazar3@gmail.com')->first();
if (!$vendor) {
    $vendor = DB::table('vendors')->where('id', 2)->first();
}
if (!$vendor) {
    fwrite(STDERR, "ERROR: seller Manzar Nazar / manzarnazar3@gmail.com not found\n");
    exit(1);
}

DB::table('vendors')->where('id', $vendor->id)->update([
    'f_name' => 'Manzar',
    'l_name' => 'Nazar',
    'status' => 1,
    'updated_at' => $now,
]);

$store = DB::table('stores')->where('vendor_id', $vendor->id)->first();
if (!$store) {
    fwrite(STDERR, "ERROR: no store for vendor {$vendor->id}\n");
    exit(1);
}

DB::table('stores')->where('id', $store->id)->update([
    'name' => 'Manzar Nazar',
    'status' => 1,
    'active' => 1,
    'module_id' => $MODULE_ID,
    'updated_at' => $now,
]);
$STORE_ID = (int) $store->id;
echo "==> Seller Manzar Nazar vendor_id={$vendor->id} store_id={$STORE_ID}\n";

$unitKg = ensureUnit('kg');
$unitPc = ensureUnit('pc');
$unitLt = ensureUnit('lt');
$unitMap = ['kg' => $unitKg, 'pc' => $unitPc, 'lt' => $unitLt];

// Categories (existing + new)
$categoryDefs = [
    ['name' => 'Frutas y Verduras', 'slug' => 'frutas-y-verduras', 'img' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Carnes y Pollo', 'slug' => 'carnes-y-pollo', 'img' => 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Lácteos', 'slug' => 'lacteos', 'img' => 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Panadería', 'slug' => 'panaderia', 'img' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Bebidas', 'slug' => 'bebidas', 'img' => 'https://images.unsplash.com/photo-1622597467836-f3285f2131b8?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Abarrotes', 'slug' => 'abarrotes', 'img' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Snacks', 'slug' => 'snacks', 'img' => 'https://images.unsplash.com/photo-1566478989037-ed2696b2ca90?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Limpieza', 'slug' => 'limpieza', 'img' => 'https://images.unsplash.com/photo-1563453392212-326f5e854473?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Congelados', 'slug' => 'congelados', 'img' => 'https://images.unsplash.com/photo-1621939514649-280e2ee25f60?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Condimentos', 'slug' => 'condimentos', 'img' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?auto=format&fit=crop&w=800&q=80'],
    // NEW
    ['name' => 'Huevos', 'slug' => 'huevos', 'img' => 'https://images.unsplash.com/photo-1582722872440-91ad1cec4e3b?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Café y Té', 'slug' => 'cafe-y-te', 'img' => 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Cuidado Personal', 'slug' => 'cuidado-personal', 'img' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=800&q=80'],
    ['name' => 'Mascotas', 'slug' => 'mascotas', 'img' => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&w=800&q=80'],
];

$productDefs = [
    // Frutas y Verduras
    ['cat' => 'frutas-y-verduras', 'name' => 'Manzana Roja', 'desc' => 'Manzanas rojas frescas, dulces y crujientes.', 'price' => 45.00, 'stock' => 100, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'frutas-y-verduras', 'name' => 'Plátano', 'desc' => 'Plátanos maduros ideales para consumo diario.', 'price' => 28.00, 'stock' => 120, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'frutas-y-verduras', 'name' => 'Tomate Saladette', 'desc' => 'Tomates frescos para ensaladas y guisos.', 'price' => 32.00, 'stock' => 90, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1546470427-e212b7d31075?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'frutas-y-verduras', 'name' => 'Aguacate Hass', 'desc' => 'Aguacate Hass maduro, cremoso y fresco.', 'price' => 55.00, 'stock' => 70, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1523049673857-eb18f1d7b578?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'frutas-y-verduras', 'name' => 'Limón Persa', 'desc' => 'Limones verdes frescos para cocina y bebidas.', 'price' => 25.00, 'stock' => 110, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1582979512210-99b6a26daf1f?auto=format&fit=crop&w=800&q=80'],
    // Carnes
    ['cat' => 'carnes-y-pollo', 'name' => 'Pechuga de Pollo', 'desc' => 'Pechuga de pollo fresca sin hueso.', 'price' => 120.00, 'stock' => 50, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'carnes-y-pollo', 'name' => 'Carne Molida de Res', 'desc' => 'Carne molida magra para guisos y hamburguesas.', 'price' => 160.00, 'stock' => 40, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1603048297172-c92544798d5a?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'carnes-y-pollo', 'name' => 'Bistec de Res', 'desc' => 'Bistec de res fresco para asar o sartén.', 'price' => 185.00, 'stock' => 35, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?auto=format&fit=crop&w=800&q=80'],
    // Lacteos
    ['cat' => 'lacteos', 'name' => 'Leche Entera 1L', 'desc' => 'Leche entera pasteurizada 1 litro.', 'price' => 28.00, 'stock' => 80, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'lacteos', 'name' => 'Queso Panela', 'desc' => 'Queso panela fresco por kilogramo.', 'price' => 95.00, 'stock' => 35, 'unit' => 'kg', 'img' => 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'lacteos', 'name' => 'Yogurt Natural', 'desc' => 'Yogurt natural sin azúcar añadida.', 'price' => 35.00, 'stock' => 60, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'lacteos', 'name' => 'Mantequilla', 'desc' => 'Mantequilla sin sal 90g.', 'price' => 32.00, 'stock' => 55, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1589985270826-4b7bb135bc9d?auto=format&fit=crop&w=800&q=80'],
    // Panaderia
    ['cat' => 'panaderia', 'name' => 'Pan de Caja', 'desc' => 'Pan de caja suave, ideal para sándwiches.', 'price' => 42.00, 'stock' => 45, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'panaderia', 'name' => 'Tortillas de Maíz', 'desc' => 'Paquete de tortillas de maíz recién hechas.', 'price' => 22.00, 'stock' => 100, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'panaderia', 'name' => 'Bolillo', 'desc' => 'Bolillos frescos del día.', 'price' => 8.00, 'stock' => 80, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&w=800&q=80'],
    // Bebidas
    ['cat' => 'bebidas', 'name' => 'Agua Purificada 1.5L', 'desc' => 'Agua purificada embotellada 1.5 litros.', 'price' => 18.00, 'stock' => 150, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'bebidas', 'name' => 'Jugo de Naranja', 'desc' => 'Jugo de naranja natural 1 litro.', 'price' => 38.00, 'stock' => 55, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'bebidas', 'name' => 'Refresco Cola 2L', 'desc' => 'Refresco de cola 2 litros.', 'price' => 35.00, 'stock' => 90, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?auto=format&fit=crop&w=800&q=80'],
    // Abarrotes
    ['cat' => 'abarrotes', 'name' => 'Arroz 1kg', 'desc' => 'Arroz blanco de grano largo, 1 kilogramo.', 'price' => 32.00, 'stock' => 200, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'abarrotes', 'name' => 'Frijol Negro 1kg', 'desc' => 'Frijol negro seco, 1 kilogramo.', 'price' => 40.00, 'stock' => 150, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1596797038530-2c107229654b?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'abarrotes', 'name' => 'Aceite Vegetal 1L', 'desc' => 'Aceite vegetal comestible 1 litro.', 'price' => 48.00, 'stock' => 70, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1474979266404-7ea4979266404?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'abarrotes', 'name' => 'Pasta Spaghetti', 'desc' => 'Pasta spaghetti 500g.', 'price' => 22.00, 'stock' => 85, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1551462147-ff290753086d?auto=format&fit=crop&w=800&q=80'],
    // Snacks
    ['cat' => 'snacks', 'name' => 'Papas Fritas', 'desc' => 'Bolsa de papas fritas clásicas.', 'price' => 25.00, 'stock' => 80, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1566478989037-ed2696b2ca90?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'snacks', 'name' => 'Galletas de Avena', 'desc' => 'Galletas de avena empaquetadas.', 'price' => 30.00, 'stock' => 65, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'snacks', 'name' => 'Chocolate Amargo', 'desc' => 'Barra de chocolate amargo 70% cacao.', 'price' => 42.00, 'stock' => 50, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1548907040-4baa42d10919?auto=format&fit=crop&w=800&q=80'],
    // Limpieza
    ['cat' => 'limpieza', 'name' => 'Detergente Líquido', 'desc' => 'Detergente líquido para ropa, 1 litro.', 'price' => 55.00, 'stock' => 40, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1610557892470-55d9e80c0bce?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'limpieza', 'name' => 'Jabón de Manos', 'desc' => 'Jabón líquido antibacterial para manos.', 'price' => 35.00, 'stock' => 50, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1600857544200-b2f666a9a2ec?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'limpieza', 'name' => 'Cloro 1L', 'desc' => 'Cloro desinfectante 1 litro.', 'price' => 28.00, 'stock' => 60, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1585421514738-01796e0a0e0e?auto=format&fit=crop&w=800&q=80'],
    // Congelados
    ['cat' => 'congelados', 'name' => 'Helado de Vainilla', 'desc' => 'Helado de vainilla 1 litro.', 'price' => 85.00, 'stock' => 30, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1563805042-7684c019e1cd?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'congelados', 'name' => 'Verduras Mixtas Congeladas', 'desc' => 'Mezcla de verduras congeladas 500g.', 'price' => 45.00, 'stock' => 40, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1459411621453-7b03977f4bfc?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'congelados', 'name' => 'Pizza Congelada', 'desc' => 'Pizza congelada lista para hornear.', 'price' => 95.00, 'stock' => 25, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=800&q=80'],
    // Condimentos
    ['cat' => 'condimentos', 'name' => 'Sal de Mesa', 'desc' => 'Sal refinada de mesa 1kg.', 'price' => 15.00, 'stock' => 100, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1518110925495-5fe2cdf747a3?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'condimentos', 'name' => 'Azúcar Estándar', 'desc' => 'Azúcar refinada 1kg.', 'price' => 28.00, 'stock' => 90, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1581441363689-1f3c3c414635?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'condimentos', 'name' => 'Pimienta Negra', 'desc' => 'Pimienta negra molida 100g.', 'price' => 35.00, 'stock' => 70, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?auto=format&fit=crop&w=800&q=80'],
    // Huevos (NEW)
    ['cat' => 'huevos', 'name' => 'Huevo Blanco Docena', 'desc' => 'Docena de huevos blancos frescos.', 'price' => 48.00, 'stock' => 90, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1582722872440-91ad1cec4e3b?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'huevos', 'name' => 'Huevo Orgánico 6pz', 'desc' => 'Huevos orgánicos de gallina libre, paquete 6.', 'price' => 55.00, 'stock' => 40, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1506976785307-8732e854ad03?auto=format&fit=crop&w=800&q=80'],
    // Cafe y Te (NEW)
    ['cat' => 'cafe-y-te', 'name' => 'Café Molido 500g', 'desc' => 'Café molido tueste medio 500g.', 'price' => 120.00, 'stock' => 45, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1447933601403-0c6688de566e?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'cafe-y-te', 'name' => 'Té Verde 20 sobres', 'desc' => 'Té verde en sobres, caja de 20.', 'price' => 45.00, 'stock' => 60, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=800&q=80'],
    // Cuidado Personal (NEW)
    ['cat' => 'cuidado-personal', 'name' => 'Shampoo 400ml', 'desc' => 'Shampoo para uso diario 400ml.', 'price' => 65.00, 'stock' => 40, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'cuidado-personal', 'name' => 'Pasta Dental', 'desc' => 'Pasta dental con flúor 100ml.', 'price' => 38.00, 'stock' => 55, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1622287180861-0c5a0a0f6f6f?auto=format&fit=crop&w=800&q=80'],
    // Mascotas (NEW)
    ['cat' => 'mascotas', 'name' => 'Croquetas Perro 1kg', 'desc' => 'Alimento seco para perro adulto 1kg.', 'price' => 95.00, 'stock' => 35, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1589924691995-400dc9fac119?auto=format&fit=crop&w=800&q=80'],
    ['cat' => 'mascotas', 'name' => 'Croquetas Gato 1kg', 'desc' => 'Alimento seco para gato adulto 1kg.', 'price' => 110.00, 'stock' => 30, 'unit' => 'pc', 'img' => 'https://images.unsplash.com/photo-1589924691995-400dc9fac119?auto=format&fit=crop&w=800&q=80'],
];

// Fix oil URL (typo guard) + toothpaste fallback
foreach ($productDefs as &$pd) {
    if ($pd['name'] === 'Aceite Vegetal 1L') {
        $pd['img'] = 'https://images.unsplash.com/photo-1474979266404-7ea4979266404?auto=format&fit=crop&w=800&q=80';
        // correct unsplash oil
        $pd['img'] = 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=800&q=80';
    }
    if ($pd['name'] === 'Pasta Dental') {
        $pd['img'] = 'https://images.unsplash.com/photo-1629198688000-71f23e745b6e?auto=format&fit=crop&w=800&q=80';
    }
    if ($pd['name'] === 'Cloro 1L') {
        $pd['img'] = 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=800&q=80';
    }
    if ($pd['name'] === 'Croquetas Gato 1kg') {
        $pd['img'] = 'https://images.unsplash.com/photo-1574158622682-e40e69881006?auto=format&fit=crop&w=800&q=80';
    }
}
unset($pd);

echo "\n==> Categories & images...\n";
$catIdBySlug = [];
$priority = 1;
$createdCats = 0;
$updatedCats = 0;

DB::beginTransaction();
try {
    foreach ($categoryDefs as $cat) {
        $file = makeFilename($datePrefix, 'jpg');
        $ok = downloadImage($cat['img'], $catDir . '/' . $file);
        if (!$ok) {
            $ok = downloadImage('https://picsum.photos/seed/' . $cat['slug'] . '/800/800.jpg', $catDir . '/' . $file);
        }
        $image = $ok ? $file : 'def.png';
        echo ($ok ? '  OK' : '  FAIL') . " category image: {$cat['name']}\n";

        $existing = DB::table('categories')
            ->where('module_id', $MODULE_ID)
            ->where(function ($q) use ($cat) {
                $q->where('slug', $cat['slug'])->orWhere('name', $cat['name']);
            })
            ->where('parent_id', 0)
            ->first();

        if ($existing) {
            DB::table('categories')->where('id', $existing->id)->update([
                'name' => $cat['name'],
                'image' => $image,
                'status' => 1,
                'priority' => $priority,
                'slug' => $cat['slug'],
                'featured' => 1,
                'updated_at' => $now,
            ]);
            $catId = (int) $existing->id;
            $updatedCats++;
            echo "  updated category #{$catId} {$cat['name']}\n";
        } else {
            $catId = (int) DB::table('categories')->insertGetId([
                'name' => $cat['name'],
                'image' => $image,
                'parent_id' => 0,
                'position' => 0,
                'status' => 1,
                'priority' => $priority,
                'module_id' => $MODULE_ID,
                'slug' => uniqueSlug($cat['name'], 'categories'),
                'featured' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $createdCats++;
            echo "  created category #{$catId} {$cat['name']}\n";
        }

        $catIdBySlug[$cat['slug']] = $catId;
        ensureStorage('App\\Models\\Category', $catId, 'image', 'public');
        upsertTranslation('App\\Models\\Category', $catId, 'name', $cat['name']);
        $priority++;
    }

    echo "\n==> Products & images (store_id={$STORE_ID})...\n";
    $createdItems = 0;
    $updatedItems = 0;
    $failedImages = 0;

    foreach ($productDefs as $i => $p) {
        if (!isset($catIdBySlug[$p['cat']])) {
            echo "  SKIP missing category for {$p['name']}\n";
            continue;
        }
        $catId = $catIdBySlug[$p['cat']];
        $file = makeFilename($datePrefix, 'jpg');
        $ok = downloadImage($p['img'], $prodDir . '/' . $file);
        if (!$ok) {
            $failedImages++;
            $ok = downloadImage('https://picsum.photos/seed/prod' . $i . '/800/800.jpg', $prodDir . '/' . $file);
        }
        $image = $ok ? $file : 'def.png';
        echo ($ok ? '  OK' : '  FAIL') . " product image: {$p['name']}\n";

        $existing = DB::table('items')
            ->where('name', $p['name'])
            ->where(function ($q) use ($STORE_ID) {
                $q->where('store_id', $STORE_ID)->orWhere('store_id', 1);
            })
            ->first();

        $payload = [
            'name' => $p['name'],
            'description' => $p['desc'],
            'image' => $image,
            'category_id' => $catId,
            'category_ids' => json_encode([['id' => (string) $catId, 'position' => 1]]),
            'price' => $p['price'],
            'stock' => $p['stock'],
            'unit_id' => $unitMap[$p['unit']] ?? $unitPc,
            'store_id' => $STORE_ID,
            'module_id' => $MODULE_ID,
            'status' => 1,
            'is_approved' => 1,
            'recommended' => 1,
            'veg' => 1,
            'available_time_starts' => '00:00:00',
            'available_time_ends' => '23:59:59',
            'maximum_cart_quantity' => 20,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('items')->where('id', $existing->id)->update($payload);
            $itemId = (int) $existing->id;
            $updatedItems++;
            echo "  updated product #{$itemId} {$p['name']} -> store {$STORE_ID}\n";
        } else {
            $slugBase = Str::slug($p['name']);
            $itemId = (int) DB::table('items')->insertGetId(array_merge($payload, [
                'variations' => json_encode([]),
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                'tax' => 0,
                'tax_type' => 'percent',
                'discount' => 0,
                'discount_type' => 'percent',
                'order_count' => 0,
                'avg_rating' => 0,
                'rating_count' => 0,
                'rating' => null,
                'images' => json_encode([]),
                'food_variations' => null,
                'slug' => $slugBase,
                'organic' => 0,
                'is_halal' => 0,
                'created_at' => $now,
            ]));
            DB::table('items')->where('id', $itemId)->update(['slug' => $slugBase . $itemId]);
            $createdItems++;
            echo "  created product #{$itemId} {$p['name']}\n";
        }

        ensureStorage('App\\Models\\Item', $itemId, 'image', 'public');
        upsertTranslation('App\\Models\\Item', $itemId, 'name', $p['name']);
        upsertTranslation('App\\Models\\Item', $itemId, 'description', $p['desc']);
    }

    // Move any leftover Demo Store grocery items to Manzar if same names already handled;
    // leave other Demo items alone if not in our list.
    DB::table('items')->where('store_id', 1)->whereIn('name', collect($productDefs)->pluck('name'))->update([
        'store_id' => $STORE_ID,
        'updated_at' => $now,
    ]);

    DB::commit();

    @exec('chown -R www-data:www-data ' . escapeshellarg($catDir) . ' ' . escapeshellarg($prodDir));

    echo "\n==== DONE ====\n";
    echo "Categories created: {$createdCats}, updated: {$updatedCats}\n";
    echo "Products created: {$createdItems}, updated: {$updatedItems}\n";
    echo "Failed primary image downloads: {$failedImages}\n";
    echo "Store items (Manzar Nazar): " . DB::table('items')->where('store_id', $STORE_ID)->count() . "\n";
    echo "Module categories: " . DB::table('categories')->where('module_id', $MODULE_ID)->where('parent_id', 0)->count() . "\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
