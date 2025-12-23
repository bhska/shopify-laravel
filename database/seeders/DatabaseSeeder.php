<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Admin User for Web Login
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );

        // 2. Mobile App User
        $mobileUser = User::firstOrCreate(
            ['email' => 'mobile@app.com'],
            ['name' => 'Mobile App User', 'password' => bcrypt('password')]
        );

        $token = $mobileUser->createToken('mobile-app-token')->plainTextToken;
        $this->command->info('Mobile App Token: '.$token);

        // 3. Sample Products (Local only, simulating synced data)
        if (Product::count() === 0) {
            Product::factory()
                ->count(10)
                ->has(Variant::factory()->count(2))
                ->create();

            $this->command->info('Seeded 10 products with variants.');
        }
    }
}
