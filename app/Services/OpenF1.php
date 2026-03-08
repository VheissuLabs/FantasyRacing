<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OpenF1
{
    protected const BASE_URL = 'https://api.openf1.org/v1';

    protected const TIMEOUT = 30;

    /** Minimum seconds between requests (3 req/s limit). */
    protected const RATE_LIMIT_INTERVAL = 0.35;

    protected ?float $lastRequestAt = null;

    public function getSessions(int $year, ?string $countryName = null, ?string $sessionName = null): Collection
    {
        $params = ['year' => $year];

        if ($countryName) {
            $params['country_name'] = $countryName;
        }

        if ($sessionName) {
            $params['session_name'] = $sessionName;
        }

        return $this->get('/sessions', $params);
    }

    public function getDrivers(int $sessionKey): Collection
    {
        return $this->get('/drivers', ['session_key' => $sessionKey]);
    }

    public function getSessionResults(int $sessionKey): Collection
    {
        return $this->get('/session_result', ['session_key' => $sessionKey]);
    }

    public function getStartingGrid(int $sessionKey): Collection
    {
        return $this->get('/starting_grid', ['session_key' => $sessionKey]);
    }

    public function getPitStops(int $sessionKey): Collection
    {
        return $this->get('/pit', ['session_key' => $sessionKey]);
    }

    public function getMeetings(int $year, ?string $countryName = null): Collection
    {
        $params = ['year' => $year];

        if ($countryName) {
            $params['country_name'] = $countryName;
        }

        return $this->get('/meetings', $params);
    }

    public function getLaps(int $sessionKey, ?int $driverNumber = null): Collection
    {
        $params = ['session_key' => $sessionKey];

        if ($driverNumber) {
            $params['driver_number'] = $driverNumber;
        }

        return $this->get('/laps', $params);
    }

    protected function get(string $endpoint, array $params = []): Collection
    {
        if ($this->lastRequestAt !== null) {
            $elapsed = microtime(true) - $this->lastRequestAt;
            $wait = self::RATE_LIMIT_INTERVAL - $elapsed;

            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }

        $this->lastRequestAt = microtime(true);

        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::BASE_URL . $endpoint, $params);
        } catch (ConnectionException) {
            return collect();
        }

        $data = $response->json();

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)->filter(fn ($item) => is_array($item));
    }
}
