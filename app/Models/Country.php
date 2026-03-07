<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    /** @use HasFactory<\Database\Factories\CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'nationality',
        'region',
        'subregion',
        'emoji',
    ];

    /**
     * Jolpica demonyms that don't match the countries dataset directly.
     *
     * @var array<string, string>
     */
    protected static array $jolpicaAliases = [
        'New Zealander' => 'New Zealand',
    ];

    /**
     * Find a country by a Jolpica nationality string (e.g. "British", "Dutch").
     * Some entries store multiple demonyms (e.g. "British, UK") so we match on
     * exact value or where the stored value starts with the given nationality.
     */
    public static function findByNationality(string $nationality): ?self
    {
        $nationality = self::$jolpicaAliases[$nationality] ?? $nationality;

        return self::where('nationality', $nationality)
            ->orWhere('nationality', 'LIKE', "{$nationality},%")
            ->orderByRaw('LENGTH(nationality) ASC')
            ->first();
    }

    public function constructors(): HasMany
    {
        return $this->hasMany(Constructor::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}
