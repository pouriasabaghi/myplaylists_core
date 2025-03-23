<?php
use App\Http\Controllers\api\v1\AuthenticationController;
use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticationController::class, 'login']);
Route::post('/logout', [AuthenticationController::class, 'logout']);
Route::post('/otp', [AuthenticationController::class, 'otp']);
Route::post('/register', [AuthenticationController::class, 'register']);
Route::get('/telegram-auth', [AuthenticationController::class, 'telegramAuth']);

Route::post('/telegram', [TelegramBotController::class, 'handle']);