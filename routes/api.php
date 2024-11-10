<?php
use App\Http\Controllers\api\v1\AuthenticationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('/songs', \App\Http\Controllers\api\v1\SongController::class)->middleware('auth:sanctum');
Route::prefix('songs')->group(function () {
    Route::get('/{id}/stream', [\App\Http\Controllers\api\v1\SongController::class, 'stream']);
    Route::post('/{id}/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'toggle']);
});

Route::get('/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'index']);


Route::prefix('playlists')->group(function () {
    Route::get('/', [App\Http\Controllers\api\v1\PlaylistController::class, 'index']);
    Route::post('/', [App\Http\Controllers\api\v1\PlaylistController::class, 'create']);
    Route::get('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'edit']);
    Route::put('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'update']);
    Route::delete('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'destroy']);
    Route::post('/{playlist}/songs', [App\Http\Controllers\api\v1\PlaylistController::class, 'addSong']);
    Route::get('/{playlist}/songs', [App\Http\Controllers\api\v1\PlaylistController::class, 'getSongs']);
    Route::delete('/{playlist}/songs/{song}', [App\Http\Controllers\api\v1\PlaylistController::class, 'removeSong']);
});

