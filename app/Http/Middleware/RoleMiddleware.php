<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Pastikan user terautentikasi melalui JWT
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Token tidak valid atau tidak ditemukan.'
            ], 401);
        }

        // Cek apakah role user ada di dalam daftar yang diizinkan
        if (!in_array(Auth::guard('api')->user()->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Ditolak. Anda tidak memiliki izin untuk rute ini (403).'
            ], 403);
        }

        return $next($request);
    }
}
