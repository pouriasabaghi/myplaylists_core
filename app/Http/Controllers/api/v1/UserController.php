<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserFormRequest;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->get();
        return response()->json($users);
    }

    public function store(UserFormRequest $request)
    {
        $user = User::create($request->validated());
        return response()->json([
            'user' => $user,
            'message' => 'User Created Successfully',
        ], 201);
    }

    public function show(string $id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    public function update(UserFormRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->validated());
        return response()->json([
            'user' => $user,
            'message' => 'User Updated Successfully',
        ]);
    }


    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json([
            'message' => 'User Deleted Successfully',   
        ]);
    }
}
