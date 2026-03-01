<?php

namespace Database\Seeders;

use App\Models\Franchise;
use App\Models\Season;
use Illuminate\Database\Seeder;

class FranchiseSeeder extends Seeder
{
    public function run(): void
    {
        $f1 = Franchise::firstOrCreate(
            ['slug' => 'f1'],
            ['name' => 'Formula 1', 'description' => 'The pinnacle of motorsport.', 'is_active' => true],
        );

        Season::firstOrCreate(
            ['franchise_id' => $f1->id, 'year' => 2026],
            ['name' => '2026', 'is_active' => true],
        );
    }
}
