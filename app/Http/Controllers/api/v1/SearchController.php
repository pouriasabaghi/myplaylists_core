<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'keyword' => 'required|string',
            ]);

            $keyword = $data['keyword'];

            if (empty($keyword))
                throw new \Exception('Keyword is required', 400);

            $userSongs = Song::where('user_id', auth()->user()->id)->where('name', 'LIKE', value: "%$keyword%")->get();

            $globalSongs = Song::where('name', 'LIKE', "%$keyword%")->get();

            return response()->json([
                'user_songs' => $userSongs,
                'global_songs' => $globalSongs,
                'success' => true
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'success' => false
            ]);
        }
    }
}
