<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return $this->error('بيانات الدخول غير صحيحة.', 401);
        }

        if (! $user->is_active) {
            return $this->error('هذا الحساب غير مفعّل، يرجى التواصل مع الإدارة.', 403);
        }

        $token = $user->createToken('admin-api-token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ], 'تم تسجيل الدخول بنجاح.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'تم تسجيل الخروج بنجاح.');
    }
}
