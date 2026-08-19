<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The channel authorization callbacks are used to
| check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('profile.{userId}.posts', function ($user, int $userId) {
    return true;
});
