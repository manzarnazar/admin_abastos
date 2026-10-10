<?php

use App\Http\Controllers\Admin\Diablero\DiableroController;
use App\Http\Controllers\Admin\Diablero\DiableroTaskController;
use App\Http\Controllers\Admin\Diablero\LoadingZoneController;
use Illuminate\Support\Facades\Route;

Route::group(['as' => 'admin.', 'middleware' => ['admin', 'current-module', 'actch:admin_panel']], function () {
    Route::group(['prefix' => 'diablero', 'as' => 'diablero.', 'middleware' => ['module:diablero']], function () {
        Route::get('list', [DiableroController::class, 'index'])->name('list');
        Route::get('add', [DiableroController::class, 'create'])->name('add');
        Route::post('add', [DiableroController::class, 'store'])->name('store');
        Route::get('edit/{id}', [DiableroController::class, 'edit'])->name('edit');
        Route::post('edit/{id}', [DiableroController::class, 'update'])->name('update');
        Route::get('status/{id}/{status}', [DiableroController::class, 'updateStatus'])->name('status');
        Route::delete('delete/{id}', [DiableroController::class, 'destroy'])->name('delete');
        Route::get('view/{id}', [DiableroController::class, 'show'])->name('view');
        Route::post('payout/{id}', [DiableroController::class, 'recordPayout'])->name('payout');

        Route::group(['prefix' => 'loading-zone', 'as' => 'loading-zone.'], function () {
            Route::get('/', [LoadingZoneController::class, 'index'])->name('index');
            Route::post('/', [LoadingZoneController::class, 'store'])->name('store');
            Route::get('edit/{id}', [LoadingZoneController::class, 'edit'])->name('edit');
            Route::post('edit/{id}', [LoadingZoneController::class, 'update'])->name('update');
            Route::get('status/{id}/{status}', [LoadingZoneController::class, 'updateStatus'])->name('status');
            Route::delete('delete/{id}', [LoadingZoneController::class, 'destroy'])->name('delete');
        });

        Route::group(['prefix' => 'task', 'as' => 'task.'], function () {
            Route::get('list/{status?}', [DiableroTaskController::class, 'index'])->name('list');
            Route::get('view/{id}', [DiableroTaskController::class, 'show'])->name('view');
            Route::post('cancel/{id}', [DiableroTaskController::class, 'cancel'])->name('cancel');
            Route::post('unassign/{id}', [DiableroTaskController::class, 'unassign'])->name('unassign');
            Route::post('assign/{id}', [DiableroTaskController::class, 'assign'])->name('assign');
        });
    });
});
