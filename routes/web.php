<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/vapid-key', function () {
    return response()->json([
        'vapid_public_key' => config('services.push.public_key'),
    ]);
});

/**
 * Broadcasting auth endpoint with JWT authentication
 */
Route::post('/broadcasting/auth', function (Request $request) {
    // Get token from Authorization header
    $token = $request->bearerToken();
    
    if (!$token) {
        return response()->json(['error' => 'No token provided'], 401);
    }
    
    try {
        // Authenticate with JWT
        $user = JWTAuth::setToken($token)->authenticate();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }
        
        // Set the authenticated user
        auth()->login($user);
        
        // Use Laravel's Broadcast facade to authenticate
        return Broadcast::auth($request);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid token: ' . $e->getMessage()], 401);
    }
})->middleware('cors');
