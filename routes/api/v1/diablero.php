<?php

use App\Http\Controllers\Api\V1\Auth\DiableroLoginController;
use App\Http\Controllers\Api\V1\DiableroController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['localization']], function () {
    Route::group(['prefix' => 'auth'], function () {
        Route::post('login', [DiableroLoginController::class, 'login'])->middleware('throttle:10,1');
        Route::post('logout', [DiableroLoginController::class, 'logout'])->middleware('diablero.api');
        Route::post('forgot-password', [DiableroLoginController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('verify-token', [DiableroLoginController::class, 'verifyToken'])->middleware('throttle:10,1');
        Route::put('reset-password', [DiableroLoginController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

    Route::group(['middleware' => ['diablero.api']], function () {
        Route::get('profile', [DiableroController::class, 'profile']);
        Route::post('update-profile', [DiableroController::class, 'updateProfile']);
        Route::post('update-active-status', [DiableroController::class, 'updateActiveStatus']);
        Route::put('update-fcm-token', [DiableroController::class, 'updateFcmToken']);
        Route::post('record-location', [DiableroController::class, 'recordLocation']);
        Route::get('notifications', [DiableroController::class, 'notifications']);
        Route::get('earnings', [DiableroController::class, 'earnings']);
        Route::get('wallet-transactions', [DiableroController::class, 'walletTransactions']);
        Route::delete('remove-account', [DiableroController::class, 'removeAccount']);

        Route::group(['prefix' => 'tasks'], function () {
            Route::get('available', [DiableroController::class, 'availableTasks']);
            Route::get('current', [DiableroController::class, 'currentTasks']);
            Route::get('history', [DiableroController::class, 'taskHistory']);
            Route::get('{id}', [DiableroController::class, 'taskDetails'])->whereNumber('id');
            Route::post('{id}/accept', [DiableroController::class, 'acceptTask'])->whereNumber('id');
            Route::post('{id}/reject', [DiableroController::class, 'rejectTask'])->whereNumber('id');
            Route::post('{id}/status', [DiableroController::class, 'updateTaskStatus'])->whereNumber('id');
            Route::post('{id}/stops/{stop}/collect', [DiableroController::class, 'collectStop'])->whereNumber(['id', 'stop']);
            Route::post('{id}/stops/{stop}/fail', [DiableroController::class, 'failStop'])->whereNumber(['id', 'stop']);
        });
    });
});
