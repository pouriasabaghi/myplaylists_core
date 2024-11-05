<?php
use App\Http\Controllers\api\v1\AuthenticationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('/songs', \App\Http\Controllers\api\v1\SongController::class)->middleware('auth:sanctum');
Route::get('/songs/{id}/stream', [\App\Http\Controllers\api\v1\SongController::class, 'stream']);
Route::post('/songs/{id}/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'toggle']);

Route::get('/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'index']);
