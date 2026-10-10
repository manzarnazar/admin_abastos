<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_notifications', 'diablero_id')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('diablero_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_notifications', 'diablero_id')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->dropColumn('diablero_id');
            });
        }
    }
};
