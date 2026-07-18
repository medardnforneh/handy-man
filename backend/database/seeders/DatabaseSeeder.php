<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            StaffRolesSeeder::class, // global staff/admin roles (P0-18)
            SkillsSeeder::class,     // bilingual trade catalog (P1-07)
        ]);
    }
}
