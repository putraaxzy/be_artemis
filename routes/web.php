<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/vapid-key', function () {
    return response()->json([
        'vapid_public_key' => config('services.push.public_key'),
    ]);
});
