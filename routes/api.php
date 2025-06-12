<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\v1\TokenController;

Route::get('/user', fn(Request $request) => [
    ...$request->user()->toArray(),
])->middleware('auth:sanctum');

Route::prefix('profile')->group(function () {
    Route::put('/', [App\Http\Controllers\api\v1\ProfileController::class, 'update'])->middleware('auth:sanctum');
    Route::get('/{user}', [App\Http\Controllers\api\v1\ProfileController::class, 'showUserProfile']);
    Route::post('/image', [App\Http\Controllers\api\v1\ProfileController::class, 'updateProfileImage']);
    Route::post('/banner', [App\Http\Controllers\api\v1\ProfileController::class, 'updateBannerImage']);
});


// Telegram bot access token
Route::get('/token', [TokenController::class, 'create'])->middleware('auth:sanctum');

Route::apiResource('/songs', \App\Http\Controllers\api\v1\SongController::class)->except(['show'])->middleware('auth:sanctum');
Route::get('/songs/top-songs', [\App\Http\Controllers\api\v1\SongController::class, 'getTopSongs'])->middleware('auth:sanctum');
Route::get('/songs/latest-songs', [\App\Http\Controllers\api\v1\SongController::class, 'getLatestSongs'])->middleware('auth:sanctum');
Route::post('/songs/{id}/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'toggle'])->middleware('auth:sanctum');
Route::post('/songs/bulk-delete', [\App\Http\Controllers\api\v1\SongController::class, 'bulkDestroy'])->middleware('auth:sanctum');

// unauthenticated users can access to single songs and stream them
Route::prefix('songs')->group(function () {
    Route::get('/{song}', [\App\Http\Controllers\api\v1\SongController::class, 'show']);
    Route::get('/{id}/stream', [\App\Http\Controllers\api\v1\SongController::class, 'stream']);
    Route::get('/{id}/download', [\App\Http\Controllers\api\v1\SongController::class, 'download']);
});

Route::get('/favorites', [\App\Http\Controllers\api\v1\FavoriteController::class, 'index'])->middleware('auth:sanctum');

// unauthenticated users can access public playlists
Route::get('playlists/{playlist}/songs', [App\Http\Controllers\api\v1\PlaylistController::class, 'getSongs']);

Route::prefix('playlists')->middleware('auth:sanctum')->group(function () {
    Route::get('/top-playlists', [App\Http\Controllers\api\v1\PlaylistController::class, 'getTopPlaylists']);
    Route::get('/latest-playlists', [App\Http\Controllers\api\v1\PlaylistController::class, 'getLatestPlaylists']);

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

Route::get('/search', [App\Http\Controllers\api\v1\SearchController::class, 'search'])->middleware('auth:sanctum');

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::apiResource('/users', App\Http\Controllers\api\v1\UserController::class);
    Route::post('artists/{artistName}/cover', [App\Http\Controllers\api\v1\ArtistController::class, 'updateCover']);
});

Route::prefix('artists')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [App\Http\Controllers\api\v1\ArtistController::class, 'index']);
    Route::get('/{artistName}', [App\Http\Controllers\api\v1\ArtistController::class, 'getSongs']);
});
