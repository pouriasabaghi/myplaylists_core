<?php
use App\Http\Controllers\api\v1\AuthenticationController;
use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticationController::class, 'login']);
Route::post('/otp', [AuthenticationController::class, 'otp']);
Route::post('/register', [AuthenticationController::class, 'register']);

Route::post('/telegram', [TelegramBotController::class, 'handle']);