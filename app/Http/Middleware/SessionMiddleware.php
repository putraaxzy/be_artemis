<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

/**
 * Session Middleware
 * 
 * Handles JWT token validation from both Authorization header and cookies
 * Priority:
 * 1. Authorization header (Bearer token)
 * 2. auth_token cookie
 */
class SessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader && $request->hasCookie('auth_token')) {
            $token = $request->cookie('auth_token');
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'User tidak ditemukan',
                    'kode' => 'USER_NOT_FOUND'
                ], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Session telah berakhir. Silakan login kembali.',
                'kode' => 'TOKEN_EXPIRED'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Token tidak valid',
                'kode' => 'TOKEN_INVALID'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Token tidak ditemukan. Silakan login.',
                'kode' => 'TOKEN_NOT_FOUND'
            ], 401);
        }

        return $next($request);
    }
}
