<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PriorityLevel;
use Illuminate\Database\Seeder;

final class PriorityLevelSeeder extends Seeder
{
    public function run(): void
    {
        PriorityLevel::factory()->count(10)->create();
    }
}
