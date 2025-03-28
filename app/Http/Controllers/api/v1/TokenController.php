<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TokenController extends Controller
{

    public function create(Request $request)
    {
        $token = encrypt([
            'email' => auth()->user()->email,
            'expires_at' => now()->addMinutes(1)->timestamp,
        ]);

        return response()->json($token);
    }

    public function isTokenValid($token)
    {
        $token = decrypt($token);

        if (Carbon::parse($token['expires_at'])->isPast() || empty($token['email']))
            return false;

        return $token;
    }
}
