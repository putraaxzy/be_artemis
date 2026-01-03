<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel untuk notifikasi per user (siswa)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Channel untuk guru notifications
Broadcast::channel('guru-notifications', function ($user) {
    return $user->role === 'guru';
});
