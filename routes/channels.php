<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Each authenticated user listens on their own private channel. Chat events
| are broadcast to both the sender's and recipient's channel so every open
| tab stays in sync without polling.
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});
