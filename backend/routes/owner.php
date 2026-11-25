<?php

use App\Http\Controllers\OwnerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/owner')->controller(OwnerController::class)->group(function () {
    Route::get('session', 'session');
    Route::post('login', 'login')->middleware('throttle:8,1');
    Route::middleware('auth')->group(function () {
        Route::post('logout', 'logout');
        Route::get('workspace', 'workspace');
        Route::get('calendar', 'calendar');
        Route::post('bookings', 'book');
        Route::patch('bookings/{id}', 'bookingStatus');
        Route::get('slots', 'slots');
        Route::post('services', 'service');
        Route::put('services/{id}', 'service');
        Route::delete('services/{id}', 'deleteService');
        Route::post('employees', 'employee');
        Route::put('employees/{id}', 'employee');
        Route::delete('employees/{id}', 'deleteEmployee');
        Route::post('profile', 'profile');
        Route::put('account', 'account');
        Route::get('threads', 'threads');
        Route::get('threads/{id}', 'messages');
        Route::post('threads/{id}', 'reply')->middleware('throttle:60,1');
    });
});
