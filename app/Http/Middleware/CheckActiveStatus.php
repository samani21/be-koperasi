<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        // Jika user berhasil lewat token JWT TAPI statusnya tidak aktif
        if (Auth::guard('api')->check() && !Auth::guard('api')->user()->is_active) {

            // Hancurkan token JWT yang sedang dipakai
            Auth::guard('api')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi Super Admin.'
            ], 403);
        }

        return $next($request);
    }
}
