<?php
use App\Http\Controllers\api\v1\FollowController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('/songs', \App\Http\Controllers\api\v1\SongController::class)->except(['show', 'stream'])->middleware('auth:sanctum');
Route::post('songs/{id}/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'toggle'])->middleware('auth:sanctum');

// unauthenticated users can access to single songs and stream them
Route::prefix('songs')->group(function () {
    Route::get('/{song}', [\App\Http\Controllers\api\v1\SongController::class, 'show']);
    Route::get('/{id}/stream', [\App\Http\Controllers\api\v1\SongController::class, 'stream']);
});

Route::get('/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'index'])->middleware('auth:sanctum');

// unauthenticated users can access public playlists
Route::get('playlists/{playlist}/songs', [App\Http\Controllers\api\v1\PlaylistController::class, 'getSongs']);

Route::prefix('playlists')->middleware('auth:sanctum')->group(function () {
    Route::get('/follow', [FollowController::class, 'index']);
    Route::post('/{playlist}/follow', [FollowController::class, 'toggle']);
    Route::get('/{playlist}/is-following', [FollowController::class, 'isFollowing']);
    
    Route::get('/', [App\Http\Controllers\api\v1\PlaylistController::class, 'index']);
    Route::post('/', [App\Http\Controllers\api\v1\PlaylistController::class, 'create']);
    Route::get('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'edit']);
    Route::put('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'update']);
    Route::delete('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'destroy']);
    Route::post('/{playlist}/songs', [App\Http\Controllers\api\v1\PlaylistController::class, 'addSong']);
    Route::delete('/{playlist}/songs/{song}', [App\Http\Controllers\api\v1\PlaylistController::class, 'removeSong']);
    
});
