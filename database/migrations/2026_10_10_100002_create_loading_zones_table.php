<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loading_zones')) {
            Schema::create('loading_zones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('zone_id')->index();
                $table->string('name');
                $table->string('address')->nullable();
                $table->decimal('latitude', 16, 10);
                $table->decimal('longitude', 16, 10);
                $table->boolean('is_default')->default(0);
                $table->boolean('status')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('zones', 'diablero_status')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->boolean('diablero_status')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('zones', 'diablero_status')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->dropColumn('diablero_status');
            });
        }
        Schema::dropIfExists('loading_zones');
    }
};
