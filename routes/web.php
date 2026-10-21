<?php

use App\Http\Controllers\WecomLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    if ($user === null) {
        return redirect('/admin/login');
    }

    return $user->is_admin
        ? redirect('/admin')
        : redirect()->route('me');
});

Route::get('/wecom', [WecomLoginController::class, 'start'])->name('wecom.start');
Route::get('/wecom/callback', [WecomLoginController::class, 'callback'])->name('wecom.callback');
Route::get('/wecom/denied', [WecomLoginController::class, 'denied'])->name('wecom.denied');
Route::get('/me', [WecomLoginController::class, 'me'])->middleware('auth')->name('me');
