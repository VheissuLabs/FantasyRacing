<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Jolpica
{
    protected const BASE_URL = 'https://api.jolpi.ca/ergast/f1';

    protected const TIMEOUT = 30;

    /** Minimum seconds between any two API requests. */
    protected const RATE_LIMIT_INTERVAL = 1.5;

    protected ?float $lastRequestAt = null;

    public function getSchedule(int $year): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}.json", ['limit' => 100]);

        return collect($response->json('MRData.RaceTable.Races', []));
    }

    public function getCircuits(int $year): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/circuits.json", ['limit' => 100]);

        return collect($response->json('MRData.CircuitTable.Circuits', []));
    }

    public function getDrivers(int $year): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/drivers.json", ['limit' => 100]);

        return collect($response->json('MRData.DriverTable.Drivers', []));
    }

    /**
     * Fetch all race results for every round of a season in a single request.
     * Returns a Collection of races, each containing a 'Results' array.
     */
    public function getAllRaceResults(int $year): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/results.json", ['limit' => 500]);

        return collect($response->json('MRData.RaceTable.Races', []));
    }

    public function getConstructors(int $year): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/constructors.json", ['limit' => 100]);

        return collect($response->json('MRData.ConstructorTable.Constructors', []));
    }

    public function getConstructorDrivers(int $year, string $constructorId): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/constructors/{$constructorId}/drivers.json", ['limit' => 100]);

        return collect($response->json('MRData.DriverTable.Drivers', []));
    }

    public function getRaceResults(int $year, int $round): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/{$round}/results.json", ['limit' => 100]);

        $races = $response->json('MRData.RaceTable.Races', []);

        return collect($races[0]['Results'] ?? []);
    }

    public function getQualifyingResults(int $year, int $round): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/{$round}/qualifying.json", ['limit' => 100]);

        $races = $response->json('MRData.RaceTable.Races', []);

        return collect($races[0]['QualifyingResults'] ?? []);
    }

    public function getSprintResults(int $year, int $round): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/{$round}/sprint.json", ['limit' => 100]);

        $races = $response->json('MRData.RaceTable.Races', []);

        return collect($races[0]['SprintResults'] ?? []);
    }

    public function getSprintQualifyingResults(int $year, int $round): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/{$round}/sprintQualifying.json", ['limit' => 100]);

        $races = $response->json('MRData.RaceTable.Races', []);

        return collect($races[0]['SprintQualifyingResults'] ?? []);
    }

    public function getPitStops(int $year, int $round): Collection
    {
        $response = $this->get(self::BASE_URL . "/{$year}/{$round}/pitstops.json", ['limit' => 100]);

        $races = $response->json('MRData.RaceTable.Races', []);

        return collect($races[0]['PitStops'] ?? []);
    }

    /**
     * Throttled HTTP GET — enforces a minimum interval between every request
     * so we stay within Jolpica's rate limit regardless of call site.
     */
    protected function get(string $url, array $params = [])
    {
        if ($this->lastRequestAt !== null) {
            $elapsed = microtime(true) - $this->lastRequestAt;
            $wait = self::RATE_LIMIT_INTERVAL - $elapsed;

            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }

        $this->lastRequestAt = microtime(true);

        return Http::timeout(self::TIMEOUT)->get($url, $params);
    }
}
