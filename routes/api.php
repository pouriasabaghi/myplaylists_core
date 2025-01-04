<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('/songs', \App\Http\Controllers\api\v1\SongController::class)->except(['show', 'stream'])->middleware('auth:sanctum');
Route::get('/songs/top-songs', [\App\Http\Controllers\api\v1\SongController::class, 'getTopSongs'])->middleware('auth:sanctum');
Route::post('/songs/{id}/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'toggle'])->middleware('auth:sanctum');
Route::post('/songs/bulk-delete', [\App\Http\Controllers\api\v1\SongController::class, 'bulkDestroy'])->middleware('auth:sanctum');

// unauthenticated users can access to single songs and stream them
Route::prefix('songs')->group(function () {
    Route::get('/{song}', [\App\Http\Controllers\api\v1\SongController::class, 'show']);
    Route::get('/{id}/stream', [\App\Http\Controllers\api\v1\SongController::class, 'stream']);
});

Route::get('/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'index'])->middleware('auth:sanctum');

// unauthenticated users can access public playlists
Route::get('playlists/{playlist}/songs', [App\Http\Controllers\api\v1\PlaylistController::class, 'getSongs']);

Route::prefix('playlists')->middleware('auth:sanctum')->group(function () {
    Route::get('/top-playlists', [App\Http\Controllers\api\v1\PlaylistController::class, 'getTopPlaylists']);

    Route::get('/follow', [App\Http\Controllers\api\v1\FollowController::class, 'index']);
    Route::post('/{playlist}/follow', [App\Http\Controllers\api\v1\FollowController::class, 'toggle']);
    Route::get('/{playlist}/is-following', action: [App\Http\Controllers\api\v1\FollowController::class, 'isFollowing']);

    Route::get('/', [App\Http\Controllers\api\v1\PlaylistController::class, 'index']);
    Route::post('/', [App\Http\Controllers\api\v1\PlaylistController::class, 'create']);
    Route::get('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'edit']);
    Route::put('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'update']);
    Route::delete('/{playlist}', [App\Http\Controllers\api\v1\PlaylistController::class, 'destroy']);
    Route::post('/{playlist}/songs', [App\Http\Controllers\api\v1\PlaylistController::class, 'addSong']);
    Route::post('/{playlist}/songs/bulk', [App\Http\Controllers\api\v1\PlaylistController::class, 'addSongs']);
    Route::delete('/{playlist}/songs/{song}', [App\Http\Controllers\api\v1\PlaylistController::class, 'removeSong']);

});

Route::get('/search', [App\Http\Controllers\api\v1\SearchController::class, 'search']);

Route::prefix('admin')->middleware('admin')->group(function(){
    Route::apiResource('/users', App\Http\Controllers\api\v1\UserController::class)->middleware('auth:sanctum');
});