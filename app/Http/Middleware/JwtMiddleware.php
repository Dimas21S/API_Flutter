<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Validasi token JWT
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'message' => 'Token tidak ditemukan'
                ], 401);
            }

            $user = JWTAuth::authenticate($token);

            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan'
                ], 401);
            }

        } catch (Exception $e) {

            return response()->json([
                'message' => 'Token tidak valid',
                'error'   => $e->getMessage(),
            ], 401);
        }

        return $next($request);
    }
}
