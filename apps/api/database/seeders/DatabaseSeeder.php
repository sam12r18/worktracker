<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('WORKTRACKER_ADMIN_EMAIL');
        $password = env('WORKTRACKER_ADMIN_PASSWORD');
        if ($email && $password) {
            User::updateOrCreate(['email' => $email],
                ['name' => env('WORKTRACKER_ADMIN_NAME', 'WorkTracker Admin'),
                    'password' => Hash::make($password), 'is_worktracker_admin' => true]);
        }
    }
}
