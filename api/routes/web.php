<?php

use App\Modules\Auth\Http\Controllers\OAuthLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Login por sessão para o fluxo OAuth (consentimento de conectores MCP).
Route::middleware('guest')->group(function (): void {
    Route::get('login', [OAuthLoginController::class, 'show'])->name('login');
    Route::post('login', [OAuthLoginController::class, 'login'])->middleware('throttle:auth');
});

Route::post('logout', [OAuthLoginController::class, 'logout'])->middleware('auth')->name('logout');
