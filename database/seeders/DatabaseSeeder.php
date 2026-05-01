<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,         // 1. Users (admin + 12 customer)
            ServiceSeeder::class,      // 2. Services (kiloan + addon)
            OrderSeeder::class,        // 3. Orders + OrderItems
            NotificationSeeder::class, // 4. Notifikasi
            ChatSeeder::class,         // 5. Chat
        ]);
    }
}