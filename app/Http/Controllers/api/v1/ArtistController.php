<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use App\Services\ArtistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArtistController extends Controller
{
    public function index()
    {
        $artists = Song::select(
            'songs.artist',
            DB::raw('COUNT(songs.id) as song_count'),
            'artists.cover'
        )
        ->leftJoin('artists', 'songs.artist', '=', 'artists.name')
        ->whereNotNull('songs.artist')
        ->groupBy('songs.artist', 'artists.cover')
        ->orderBy('song_count', 'desc')
        ->take(20)
        ->get();

        return response()->json($artists);
    }


    public function getSongs(string $artistName)
    {
        $songs = Song::where('artist', "LIKE", "%$artistName")->get();

        return response()->json($songs);
    }


    public function updateCover(Request $request, string $artistName, ArtistService $service)
    {
        $service->updateCoverHandler($request, $artistName);

        return response()->json(['message' => 'Cover updated successfully']);
    }
}
