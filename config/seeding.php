<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeder credentials
    |--------------------------------------------------------------------------
    |
    | No credentials are hardcoded in the seeders. Set these in the
    | environment when you deliberately want to create a super admin or demo
    | users. When SUPER_ADMIN_* is missing outside production a random
    | password is generated and printed once by the seeder.
    |
    */

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'demo_user_password' => env('DEMO_USER_PASSWORD'),

];
