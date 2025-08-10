<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\ProfileService;
use App\Models\User;
use App\Http\Resources\UserProfileResource;
use Illuminate\Validation\Rule;
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'telegram_username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_@]+$/'],
            'telegram_channel' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(@[a-zA-Z][a-zA-Z0-9_]*|-[0-9]+|[a-zA-Z][a-zA-Z0-9_]*)$/',
                Rule::unique('users', 'telegram_channel')->ignore(auth()->user()?->id),
            ],
            'bio' => 'nullable|string',
        ]);

        $data['name'] = strtolower($data['name']);

        if (str_starts_with($data['telegram_username'], '@'))
            $data['telegram_username'] = substr($data['telegram_username'], 1);

        if (str_starts_with($data['telegram_channel'], '@'))
            $data['telegram_channel'] = substr($data['telegram_channel'], 1);

        auth()->user()->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'success' => true,
        ]);
    }

    public function updateProfileImage(ProfileService $profileService)
    {
        $data = request()->validate([
            'file' => 'required|mimetypes:image/jpeg,image/jpg,image/png,image/webp',
        ]);
        $image = $data['file'];

        $user = auth()->user();
        if ($user->avatar)
            Storage::disk('public')->delete($user->avatar);


        $path = $profileService->upload($image, 'profiles', 300, 300);

        $user->update([
            'avatar' => $path,
        ]);

        return response()->json([
            'message' => 'Avatar updated successfully',
            'data' => ['avatar' => $path],
            'success' => true,
        ]);
    }

    public function updateBannerImage(ProfileService $profileService)
    {
        $data = request()->validate([
            'file' => 'required|mimetypes:image/jpeg,image/jpg,image/png,image/webp',
        ]);
        $image = $data['file'];

        $user = auth()->user();
        if ($user->banner)
            Storage::disk('public')->delete($user->banner);


        $path = $profileService->upload($image, 'banners', 1200, 300);

        $user->update([
            'banner' => $path,
        ]);

        return response()->json([
            'message' => 'Banner updated successfully',
            'data' => ['banner' => $path],
            'success' => true,
        ]);
    }

    public function showUserProfile(Request $request, User $user)
    {
        return response()->json(new UserProfileResource($user));
    }
}
