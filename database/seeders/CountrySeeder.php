<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = json_decode(
            file_get_contents(database_path('data/countries.json')),
            true,
        );

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['iso2' => $country['iso2']],
                [
                    'name' => $country['name'],
                    'iso3' => $country['iso3'],
                    'nationality' => $country['nationality'] ?? null,
                    'region' => $country['region'] ?? null,
                    'subregion' => $country['subregion'] ?? null,
                    'emoji' => $country['emoji'] ?? null,
                ],
            );
        }
    }
}
