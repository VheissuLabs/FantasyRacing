<?php

namespace App\Console\Commands;

use App\Models\Constructor;
use App\Models\Country;
use App\Models\Event;
use App\Models\Franchise;
use App\Models\Season;
use App\Models\SeasonConstructor;
use App\Models\SeasonDriver;
use App\Models\Track;
use App\Services\F1DataService;
use App\Services\Jolpica;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncF1Season extends Command
{
    protected $signature = 'f1:sync
        {year : The season year to import and sync (e.g. 2023)}
        {--fresh : Delete and re-seed the season before syncing}';

    protected $description = 'Seed and sync a full F1 season (tracks, events, constructors, drivers, results)';

    /**
     * @var array<string, int>
     */
    protected array $typeOffset = [
        'sprint_qualifying' => 1,
        'qualifying' => 2,
        'sprint' => 3,
        'race' => 4,
    ];

    public function handle(Jolpica $jolpica, F1DataService $f1): int
    {
        $year = (int) $this->argument('year');

        $this->info("Starting full sync for the {$year} F1 season...");
        $this->newLine();

        if (! $this->seedSeason($year, $jolpica, $f1)) {
            return Command::FAILURE;
        }

        $this->newLine();

        $this->call('f1:sync-results', [
            '--season' => $year,
        ]);

        $this->newLine();
        $this->info("{$year} F1 season sync complete.");

        return Command::SUCCESS;
    }

    protected function seedSeason(int $year, Jolpica $jolpica, F1DataService $f1): bool
    {
        $franchise = Franchise::firstOrCreate(
            ['slug' => 'f1'],
            [
                'name' => 'Formula 1',
                'description' => 'Formula 1 World Championship',
                'is_active' => true,
            ],
        );

        $existingSeason = Season::where('franchise_id', $franchise->id)->where('year', $year)->first();

        if ($existingSeason && $this->option('fresh')) {
            $this->warn("Deleting existing {$year} season data...");
            $existingSeason->delete();
            $existingSeason = null;
        }

        if ($existingSeason) {
            $season = $existingSeason;
            $this->info("Using existing season: {$season->name}");
        } else {
            $season = Season::create([
                'franchise_id' => $franchise->id,
                'name' => (string) $year,
                'year' => $year,
                'is_active' => false,
            ]);
            $this->info("Created season: {$season->name}");
        }

        // --- Schedule & Tracks ---
        $this->info('Fetching schedule from Jolpica...');

        $schedule = $jolpica->getSchedule($year);

        $this->info("  Found {$schedule->count()} rounds.");

        $officialNames = $jolpica->getCircuits($year)
            ->keyBy(fn ($circuit) => mb_strtolower(trim(Str::ascii($circuit['Location']['locality']))))
            ->map(fn ($circuit) => $circuit['circuitName']);

        $this->info('Creating tracks and events...');

        foreach ($schedule as $race) {
            $locality = $race['Circuit']['Location']['locality'];
            $localityKey = mb_strtolower(trim(Str::ascii($locality)));
            $officialName = $officialNames[$localityKey] ?? $race['Circuit']['circuitName'];
            $countryName = $race['Circuit']['Location']['country'];

            $country = Country::where('name', $countryName)->first();

            $track = Track::firstOrCreate(
                ['franchise_id' => $franchise->id, 'location' => $locality],
                ['country_id' => $country?->id, 'name' => $officialName, 'country' => $countryName],
            );

            $this->createEventsForRound($season, $track, $race);
            $this->line("  Round {$race['round']}: {$officialName}");
        }

        // --- Constructors & Drivers ---
        $this->info('Fetching constructors and drivers...');

        /** @var array<string, array{driverId: string, number: int, givenName: string, familyName: string, nationality: string|null, permanentNumber: int, constructorId: string, constructorName: string}> $allDriverData */
        $allDriverData = [];

        $constructors = $jolpica->getConstructors($year);

        // Build a set of driver IDs who appeared in qualifying or race sessions (not practice)
        $raceDriverIds = $jolpica->getAllRaceResults($year)
            ->flatMap(fn ($race) => collect($race['Results'] ?? [])->pluck('Driver.driverId'))
            ->unique();

        $qualDriverIds = $jolpica->getAllQualifyingResults($year)
            ->flatMap(fn ($race) => collect($race['QualifyingResults'] ?? [])->pluck('Driver.driverId'))
            ->unique();

        $competitiveDriverIds = $raceDriverIds->merge($qualDriverIds)->unique();

        // Get drivers from constructor endpoints, but only keep those who raced or qualified
        foreach ($constructors as $constructorData) {
            $constructorId = $constructorData['constructorId'];
            $constructorName = $constructorData['name'];

            $drivers = $jolpica->getConstructorDrivers($year, $constructorId);

            foreach ($drivers as $driverData) {
                $driverId = $driverData['driverId'];

                // Skip practice-only drivers
                if ($competitiveDriverIds->isNotEmpty() && ! $competitiveDriverIds->contains($driverId)) {
                    continue;
                }

                $permanentNumber = isset($driverData['permanentNumber'])
                    ? (int) $driverData['permanentNumber']
                    : 0;

                $allDriverData[$driverId] = [
                    'driverId' => $driverId,
                    'number' => $permanentNumber,
                    'givenName' => $driverData['givenName'],
                    'familyName' => $driverData['familyName'],
                    'nationality' => $driverData['nationality'] ?? null,
                    'permanentNumber' => $permanentNumber,
                    'constructorId' => $constructorId,
                    'constructorName' => $constructorName,
                ];
            }
        }

        $this->line('  Found ' . count($allDriverData) . ' unique drivers.');

        // --- Constructors ---
        $constructorsById = [];

        foreach (collect($allDriverData)->pluck('constructorId', 'constructorId')->unique() as $constructorId) {
            $constructorName = collect($allDriverData)->firstWhere('constructorId', $constructorId)['constructorName'];

            $constructor = Constructor::firstOrCreate(
                ['franchise_id' => $franchise->id, 'jolpica_constructor_id' => $constructorId],
                ['name' => $constructorName, 'slug' => Str::slug($constructorName), 'is_active' => true],
            );

            $constructorsById[$constructorId] = $constructor;

            SeasonConstructor::firstOrCreate([
                'season_id' => $season->id,
                'constructor_id' => $constructor->id,
            ]);

            $this->line("  Constructor: {$constructorName}");
        }

        // --- Drivers ---
        foreach ($allDriverData as $data) {
            $jolpicaDriver = array_merge($data, [
                'nationality' => $data['nationality'] ?? $nationalityMap[$data['permanentNumber']] ?? null,
            ]);

            $driver = $f1->resolveDriver($jolpicaDriver, $franchise);

            SeasonDriver::firstOrCreate(
                ['season_id' => $season->id, 'driver_id' => $driver->id, 'number' => $data['number']],
                ['constructor_id' => $constructorsById[$data['constructorId']]->id, 'effective_from' => "{$year}-01-01"],
            );

            $this->line("  Driver: {$driver->name} (#{$data['number']}) — {$data['constructorName']}");
        }

        $this->newLine();
        $this->info("{$year} F1 season seeded successfully!");

        return true;
    }

    protected function createEventsForRound(Season $season, Track $track, array $race): void
    {
        $round = (int) $race['round'];
        $gpName = $race['raceName'];

        if (isset($race['SprintQualifying'])) {
            $this->upsertEvent($season, $track, $round, 'sprint_qualifying',
                "{$gpName} Sprint Qualifying",
                $race['SprintQualifying']['date'],
                $race['SprintQualifying']['time'] ?? null,
            );
        }

        if (isset($race['Qualifying'])) {
            $this->upsertEvent($season, $track, $round, 'qualifying',
                "{$gpName} Qualifying",
                $race['Qualifying']['date'],
                $race['Qualifying']['time'] ?? null,
            );
        }

        if (isset($race['Sprint'])) {
            $this->upsertEvent($season, $track, $round, 'sprint',
                "{$gpName} Sprint",
                $race['Sprint']['date'],
                $race['Sprint']['time'] ?? null,
            );
        }

        $this->upsertEvent($season, $track, $round, 'race',
            $gpName,
            $race['date'],
            $race['time'] ?? null,
        );
    }

    protected function upsertEvent(Season $season, Track $track, int $round, string $type, string $name, string $date, ?string $time): Event
    {
        $scheduledAt = $date . 'T' . ($time ? rtrim($time, 'Z') : '00:00:00');
        $isPast = now()->isAfter($scheduledAt);

        return Event::updateOrCreate(
            [
                'season_id' => $season->id,
                'round' => $round,
                'type' => $type,
            ],
            [
                'track_id' => $track->id,
                'name' => $name,
                'scheduled_at' => $scheduledAt,
                'locked_at' => $isPast ? $scheduledAt : null,
                'status' => $isPast ? 'completed' : 'scheduled',
                'sort_order' => $round * 10 + $this->typeOffset[$type],
            ],
        );
    }
}
