<?php

use App\Http\Controllers\MarketplaceController;
use Illuminate\Support\Facades\Route;

Route::get('categories', fn () => config('booking.categories'));
Route::controller(MarketplaceController::class)->group(function () {
    Route::get('salons', 'index')->middleware('throttle:120,1');
    Route::get('salons/{slug}', 'show');
    Route::get('salons/{slug}/slots', 'slots')->middleware('throttle:120,1');
    Route::get('chats/{token}', 'chat')->middleware('throttle:120,1');
    Route::middleware('throttle:public-writes')->group(function () {
        Route::post('salons/{slug}/bookings', 'book');
        Route::post('reviews/{token}', 'review');
        Route::post('salons/{slug}/chats', 'startChat');
        Route::post('chats/{token}', 'message');
    });
});
