<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUser
{
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->profile()->create([
                'role' => $data['role'],
            ]);

            // Only fire the Registered event for business owners
            // This triggers Laravel's built-in verification email
            if ($data['role'] === 'business_owner') {
                event(new Registered($user));
            }

            return $user;
        });
    }
}
