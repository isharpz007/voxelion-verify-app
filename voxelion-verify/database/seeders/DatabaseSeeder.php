<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // No seed data — participants are created via signed OpinionPadi
        // hand-offs, and reviewer access uses env-configured credentials.
    }
}
