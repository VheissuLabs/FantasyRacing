<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Driver;
use App\Models\Franchise;
use Illuminate\Support\Str;

class F1DataService
{
    /**
     * Find or create a driver from a Jolpica Driver object, updating
     * country_id if it was previously unresolved.
     *
     * @param  array{givenName: string, familyName: string, nationality?: string}  $jolpicaDriver
     */
    public function resolveDriver(array $jolpicaDriver, Franchise $franchise): Driver
    {
        $name = trim($jolpicaDriver['givenName'].' '.$jolpicaDriver['familyName']);
        $country = isset($jolpicaDriver['nationality'])
            ? Country::findByNationality($jolpicaDriver['nationality'])
            : null;

        $driver = Driver::firstOrCreate(
            ['franchise_id' => $franchise->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'country_id' => $country?->id, 'is_active' => true],
        );

        if (! $driver->country_id && $country) {
            $driver->update(['country_id' => $country->id]);
        }

        return $driver;
    }
}
