<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if (Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'User logged in successfully',
                'success' => true,
                'user' => Auth::user(),
            ]);
        }

        return response()->json([
            'message' => 'The provided credentials do not match our records.',
            'success' => false,
        ], 401);
    }

    public function otp(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            ]);
            $email = $data['email'];

            $code = rand(10000, 99999);
            session()->put('code', $code);

            \Mail::to($email)->send(new \App\Mail\OTP($code));

            return response()->json([
                'message' => "Code sent to $email, please also check your spam folder",
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response()->json(data: [
                'message' => $th->getMessage(),
                'success' => false,
            ]);
        }
    }

    public function register(Request $request)
    {
        $credentials = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'code' => ['required', 'string'],
        ]);

        $sentCode = session()->get('code');
        $enteredCode = $credentials['code'];

        if ($sentCode != $enteredCode) {
            throw new \Exception("Code is not valid", 403);
        }

        $user = User::create([
            'name' => $credentials['name'],
            'email' => $credentials['email'],
            'password' => Hash::make($credentials['password']),
            'role' => 'uploader',
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);
    }
}
