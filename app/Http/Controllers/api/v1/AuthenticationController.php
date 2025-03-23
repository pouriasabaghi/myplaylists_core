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
    
    public function logout(){
        Auth::logout();
        return response()->json([
            'message' => 'User logged out successfully',
            'success' => true,
        ]);
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

    public function telegramAuth(Request $request)
    {
        if (Auth::check())
            return redirect(config('app.frontend_url'));


        $authData = $this->checkTelegramAuthorization($request->all());
        $user = User::firstWhere('telegram_id', $authData['id']);

        if (!$user)
            $user = User::create([
                'name' => "{$authData['first_name']}_" . uniqid(),
                'telegram_id' => $authData['id'],
                'telegram_name' => $authData['username'] ?? null,
                'nickname' => $authData['first_name'] ?? null,
                'role' => 'uploader',
                'password' => bcrypt(\Illuminate\Support\Str::random(16))
            ]);


        Auth::login($user);

        return redirect(config('app.frontend_url'));
    }

    /**
     * Check Authorization
     * @link https://gist.github.com/anonymous/6516521b1fb3b464534fbc30ea3573c2
     * @param array $authData
     * @throws \Exception
     */
    public function checkTelegramAuthorization($authData)
    {
        $botToken = config('app.telegram_bot_token');

        $check_hash = $authData['hash'];
        unset($authData['hash']);
        $data_check_arr = [];
        foreach ($authData as $key => $value) {
            $data_check_arr[] = $key . '=' . $value;
        }
        sort($data_check_arr);
        $data_check_string = implode("\n", $data_check_arr);
        $secret_key = hash('sha256', $botToken, true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);
        if (strcmp($hash, $check_hash) !== 0) {
            throw new \Exception('Data is NOT from Telegram');
        }
        if ((time() - $authData['auth_date']) > 86400) {
            throw new \Exception('Data is outdated');
        }
        return $authData;
    }

}
