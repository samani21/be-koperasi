<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->apiService->error(
                'Harap isi Email/NIK dan Password',
                422,
                $validator->errors()
            );
        }

        $loginId = trim($request->login_id);

        $throttleKey = strtolower($loginId) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->apiService->error(
                "Terlalu banyak percobaan. Silakan coba lagi dalam {$seconds} detik.",
                429
            );
        }

        $loginType = filter_var($loginId, FILTER_VALIDATE_EMAIL) ? 'email' : 'nik';

        $credentials = [
            $loginType => $loginId,
            'password' => $request->password,
        ];

        $token = Auth::guard('api')->attempt($credentials);

        if (!$token) {
            RateLimiter::hit($throttleKey, 60);

            $retriesLeft = RateLimiter::retriesLeft($throttleKey, 5);
            return $this->apiService->error("Email/NIK atau password salah. Sisa percobaan: {$retriesLeft}x", 401);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::guard('api')->user();

        if (!$user->is_active) {
            Auth::guard('api')->logout();
            return $this->apiService->error('Akun Anda dinonaktifkan. Silakan hubungi Super Admin.', 403);
        }

        $user->load('member');

        $dataUser = [
            'name' => $user->name,
            'role' => $user->role,
            'email' => $user->email,
            'nik' => $user->nik,
            'is_active' => $user->is_active,
            // Langsung ambil path mentahnya
            'photo' => $user->member->photo ?? null
        ];
        $data = [
            'user'  => $dataUser,
            'token' => $token,
            'role'  => $user->role
        ];

        return $this->apiService->successWithData($data, 'Login berhasil', 200);
    }


    public function me()
    {
        $user = Auth::guard('api')->user()->load('member');

        $dataUser = [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $user->role,
            'nik' => $user->nik,
            // Langsung ambil path mentahnya
            'photo' => $user->member->photo ?? null
        ];

        return $this->apiService->successWithData($dataUser, 'Data profil berhasil diambil', 200);
    }
}
