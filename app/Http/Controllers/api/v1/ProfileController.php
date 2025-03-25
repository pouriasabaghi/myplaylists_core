<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'telegram_username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_@]+$/'],
        ]);

        $data['name'] = strtolower($data['name']);

        if (str_starts_with($data['telegram_username'], '@'))
            $data['telegram_username'] = substr($data['telegram_username'], 1);

        auth()->user()->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'success' => true,
        ]);
    }
}
