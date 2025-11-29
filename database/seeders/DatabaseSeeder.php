<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a known test user only if not present (safe/idempotent)
        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // Call ProductSeeder idempotently to ensure products exist for tests
        if (class_exists(\Database\Seeders\ProductSeeder::class)) {
            $this->call(\Database\Seeders\ProductSeeder::class);
        }
    }
}
