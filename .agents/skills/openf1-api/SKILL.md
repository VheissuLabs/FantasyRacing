---
name: openf1-api
description: 'Integrates with the OpenF1 API for Formula 1 data. Activates when syncing race data, fetching session results, working with driver/constructor/track data from OpenF1, building seeders that pull F1 data, or when the user mentions OpenF1, F1 API, sync results, race data, session data, or lap data.'
license: MIT
metadata:
    author: fantasy-racing
---

# OpenF1 API Integration

## When to Apply

Activate this skill when:

- Syncing race results or session data from OpenF1
- Creating seeders that pull F1 data
- Working with the `App\Services\OpenF1` service class
- Mapping OpenF1 data fields to local models
- Debugging OpenF1 API responses

## API Overview

- **Base URL:** `https://api.openf1.org/v1`
- **Auth:** None required for historical data (2023+). Live data requires a paid subscription.
- **Format:** JSON (default) or CSV (add `csv=true`)
- **Rate limits:** 3 req/s, 30 req/min (free tier)
- **Data available from:** 2023 season onwards
- **Use `latest` as a value for `session_key` or `meeting_key`** to get the current/latest session or meeting.

## Service Class

The app has `App\Services\OpenF1` (`app/Services/OpenF1.php`) which wraps the API. Available methods:

| Method                                                               | Endpoint          | Description                        |
| -------------------------------------------------------------------- | ----------------- | ---------------------------------- |
| `getSessions(int $year, ?string $countryName, ?string $sessionName)` | `/sessions`       | Get sessions for a year            |
| `getDrivers(int $sessionKey)`                                        | `/drivers`        | Get drivers in a session           |
| `getSessionResults(int $sessionKey)`                                 | `/session_result` | Get standings after a session      |
| `getStartingGrid(int $sessionKey)`                                   | `/starting_grid`  | Get starting grid for a race       |
| `getPitStops(int $sessionKey)`                                       | `/pit`            | Get pit stop data                  |
| `getMeetings(int $year, ?string $countryName)`                       | `/meetings`       | Get meetings (Grand Prix weekends) |
| `getOvertakes(int $sessionKey)`                                      | `/overtakes`      | Get overtake data (races only)     |
| `getLaps(int $sessionKey, ?int $driverNumber)`                       | `/laps`           | Get lap data                       |

## API Endpoints & Response Shapes

### Sessions (`/sessions`)

Returns session info for a Grand Prix weekend. Filter by `year`, `country_name`, `session_name`, `session_type`.

```json
{
    "circuit_key": 7,
    "circuit_short_name": "Spa-Francorchamps",
    "country_code": "BEL",
    "country_key": 16,
    "country_name": "Belgium",
    "date_end": "2023-07-29T15:35:00+00:00",
    "date_start": "2023-07-29T15:05:00+00:00",
    "gmt_offset": "02:00:00",
    "location": "Spa-Francorchamps",
    "meeting_key": 1216,
    "session_key": 9140,
    "session_name": "Sprint Qualifying",
    "session_type": "Sprint Qualifying",
    "year": 2023
}
```

**Session types:** `Practice`, `Qualifying`, `Sprint Qualifying`, `Sprint`, `Race`

### Meetings (`/meetings`)

Returns Grand Prix weekend info. Filter by `year`, `country_name`.

```json
{
    "circuit_key": 61,
    "circuit_short_name": "Singapore",
    "circuit_type": "Temporary - Street",
    "country_code": "SGP",
    "country_key": 157,
    "country_name": "Singapore",
    "date_end": "2026-10-11T14:00:00+00:00",
    "date_start": "2026-10-09T09:30:00+00:00",
    "gmt_offset": "08:00:00",
    "location": "Marina Bay",
    "meeting_key": 1296,
    "meeting_name": "Singapore Grand Prix",
    "meeting_official_name": "FORMULA 1 SINGAPORE AIRLINES SINGAPORE GRAND PRIX 2026",
    "year": 2026
}
```

### Drivers (`/drivers`)

Returns driver info for a session. Filter by `session_key`, `driver_number`.

```json
{
    "broadcast_name": "M VERSTAPPEN",
    "country_code": "NED",
    "driver_number": 1,
    "first_name": "Max",
    "full_name": "Max VERSTAPPEN",
    "headshot_url": "https://...",
    "last_name": "Verstappen",
    "meeting_key": 1219,
    "name_acronym": "VER",
    "session_key": 9158,
    "team_colour": "3671C6",
    "team_name": "Red Bull Racing"
}
```

**Note:** `country_code` is deprecated and will be removed at end of 2026 season. It uses FIA/IOC codes, NOT ISO 3166-1 alpha-3. See "Country Code Mapping" below.

### Session Result (`/session_result`)

Returns standings after a session. Filter by `session_key`, `driver_number`, `position`.

```json
{
    "dnf": false,
    "dns": false,
    "dsq": false,
    "driver_number": 1,
    "duration": 77.565,
    "gap_to_leader": 0,
    "number_of_laps": 24,
    "meeting_key": 1143,
    "position": 1,
    "session_key": 7782
}
```

### Pit Stops (`/pit`)

Filter by `session_key`, `driver_number`, `lap_number`.

```json
{
    "date": "2025-10-26T20:46:37.358000+00:00",
    "driver_number": 16,
    "lane_duration": 22.215,
    "lap_number": 31,
    "meeting_key": 1272,
    "pit_duration": 22.215,
    "session_key": 9877,
    "stop_duration": 2.2
}
```

**Note:** `pit_duration` is deprecated (use `lane_duration`). `stop_duration` (stationary time) only available from 2024 US GP onwards.

### Laps (`/laps`)

Filter by `session_key`, `driver_number`, `lap_number`.

```json
{
    "date_start": "2023-09-16T13:59:07.606000+00:00",
    "driver_number": 63,
    "duration_sector_1": 26.966,
    "duration_sector_2": 38.657,
    "duration_sector_3": 26.12,
    "i1_speed": 307,
    "i2_speed": 277,
    "is_pit_out_lap": false,
    "lap_duration": 91.743,
    "lap_number": 8,
    "meeting_key": 1219,
    "segments_sector_1": [2049, 2049, 2049, 2051, 2049, 2051, 2049, 2049],
    "segments_sector_2": [2049, 2049, 2049, 2049, 2049, 2049, 2049, 2049],
    "segments_sector_3": [2048, 2048, 2048, 2048, 2048, 2064, 2064, 2064],
    "session_key": 9161,
    "st_speed": 298
}
```

### Starting Grid (`/starting_grid`)

Filter by `session_key`, `driver_number`, `position`.

```json
{
    "position": 1,
    "driver_number": 1,
    "lap_duration": 76.732,
    "meeting_key": 1143,
    "session_key": 7783
}
```

### Stints (`/stints`)

Filter by `session_key`, `driver_number`, `stint_number`, `compound`.

```json
{
    "compound": "SOFT",
    "driver_number": 16,
    "lap_end": 20,
    "lap_start": 1,
    "meeting_key": 1219,
    "session_key": 9165,
    "stint_number": 1,
    "tyre_age_at_start": 3
}
```

**Compounds:** `SOFT`, `MEDIUM`, `HARD`, `INTERMEDIATE`, `WET`

### Overtakes (`/overtakes`)

Only available during races. Filter by `session_key`, `overtaking_driver_number`, `overtaken_driver_number`, `position`.

```json
{
    "date": "2024-11-03T15:50:07.565000+00:00",
    "meeting_key": 1249,
    "overtaken_driver_number": 4,
    "overtaking_driver_number": 63,
    "position": 1,
    "session_key": 9636
}
```

### Championship Drivers (`/championship_drivers`) (beta)

Only for race sessions. Filter by `session_key`, `driver_number`.

```json
{
    "driver_number": 4,
    "meeting_key": 1276,
    "points_current": 423,
    "points_start": 408,
    "position_current": 1,
    "position_start": 1,
    "session_key": 9839
}
```

### Championship Teams (`/championship_teams`) (beta)

Only for race sessions. Filter by `session_key`, `team_name`.

```json
{
    "meeting_key": 1276,
    "points_current": 833,
    "points_start": 800,
    "position_current": 1,
    "position_start": 1,
    "session_key": 9839,
    "team_name": "McLaren"
}
```

### Other Endpoints (high-frequency telemetry)

- **Car Data** (`/car_data`) — brake, drs, gear, rpm, speed, throttle at ~3.7Hz
- **Location** (`/location`) — x, y, z coordinates at ~3.7Hz
- **Intervals** (`/intervals`) — gap to leader, interval to car ahead (~4s updates, races only)
- **Position** (`/position`) — driver position changes throughout session
- **Race Control** (`/race_control`) — flags, safety car, session status, incidents
- **Team Radio** (`/team_radio`) — audio recording URLs for driver-pit comms
- **Weather** (`/weather`) — track temp, air temp, humidity, wind, rain

## Data Filtering

All endpoints support filtering with comparison operators on any attribute:

- Exact: `?driver_number=1`
- Greater than: `?speed>=315`
- Less than: `?lap_number<=3`
- Time-based: `?date>=2023-09-16T13:00:00&date<2023-09-16T14:00:00`
- Multiple values: `?driver_number=1&driver_number=4` (OR logic)

## Country Code Mapping (FIA/IOC to ISO 3166-1 alpha-3)

OpenF1 `country_code` for drivers uses FIA/IOC codes which sometimes differ from ISO 3166-1 alpha-3. The `country_code` field is deprecated and will be removed at end of 2026 season.

Common mismatches:

| FIA/IOC Code | ISO 3166-1 alpha-3 | Country              |
| ------------ | ------------------ | -------------------- |
| NED          | NLD                | Netherlands          |
| GER          | DEU                | Germany              |
| MON          | MCO                | Monaco               |
| DEN          | DNK                | Denmark              |
| SUI          | CHE                | Switzerland          |
| RSA          | ZAF                | South Africa         |
| PHI          | PHL                | Philippines          |
| POR          | PRT                | Portugal             |
| UAE          | ARE                | United Arab Emirates |
| CHI          | CHL                | Chile                |

When resolving `country_code` to the `countries` table, try `iso3` first, then fall back to the mapping above. See `resolveCountryFromFiaCode()` in `database/seeders/F12023Seeder.php` and `app/Console/Commands/BackfillCountryIds.php`.

## Local Model Mapping

| OpenF1 Field              | Local Model   | Local Field               | Notes                         |
| ------------------------- | ------------- | ------------------------- | ----------------------------- |
| `session_key`             | `Event`       | `openf1_session_key`      | Unique session identifier     |
| `driver_number`           | `Driver`      | `openf1_driver_number`    | Driver lookup key             |
| `team_name`               | `Constructor` | `openf1_team_name`        | Constructor lookup key        |
| `country_name` (sessions) | `Country`     | `name`                    | Exact match for track country |
| `country_code` (drivers)  | `Country`     | `iso3` (with FIA mapping) | See mapping table above       |
| `circuit_key`             | —             | —                         | Used to deduplicate tracks    |
| `circuit_short_name`      | `Track`       | `name`                    |                               |
| `location`                | `Track`       | `location`                |                               |
| `full_name`               | `Driver`      | `name`                    |                               |
| `headshot_url`            | `Driver`      | `photo_path`              |                               |

## Existing Commands & Seeders

- **`php artisan openf1:sync-results {event_id?}`** — Syncs race results and pit stops from OpenF1 for events with an `openf1_session_key`. See `app/Console/Commands/SyncOpenF1Results.php`.
- **`F12023Seeder`** — Seeds the full 2023 F1 season (franchise, season, tracks, events, constructors, drivers) from OpenF1. See `database/seeders/F12023Seeder.php`.
- **`php artisan app:backfill-country-ids`** — Backfills `country_id` on existing drivers and tracks from their text fields. See `app/Console/Commands/BackfillCountryIds.php`.
