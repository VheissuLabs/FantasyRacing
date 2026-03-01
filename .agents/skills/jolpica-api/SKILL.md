---
name: jolpica-api
description: 'Integrates with the Jolpica (Ergast successor) API for official F1 reference data. Activates when fetching official circuit names, track data, driver standings, constructor standings, race results by season, or when the user mentions Jolpica, Ergast, official track names, circuit names, or F1 championship data.'
license: MIT
metadata:
    author: fantasy-racing
---

# Jolpica API Integration

## When to Apply

Activate this skill when:

- Fetching official circuit/track names
- Backfilling or enriching track data
- Getting driver or constructor championship standings
- Working with the `App\Services\Jolpica` service class

## API Overview

- **Base URL:** `https://api.jolpi.ca/ergast/f1`
- **Auth:** None required
- **Format:** JSON (add `.json` to endpoint)
- **Pagination:** Use `limit` and `offset` query params. Default limit is 30; use `limit=100` for full season data.
- **Successor to:** The Ergast API (same data, same structure)

## Service Class

The app has `App\Services\Jolpica` (`app/Services/Jolpica.php`). Available methods:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `getCircuits(int $year)` | `/{year}/circuits.json` | Get all circuits for a season |
| `getAllCircuits()` | `/circuits.json` | Get all circuits ever (limit 200) |

## API Endpoints & Response Shapes

### Circuits (`/{year}/circuits.json` or `/circuits.json`)

Returns circuit info. Supports `limit` and `offset` for pagination.

```json
{
    "circuitId": "bahrain",
    "url": "http://en.wikipedia.org/wiki/Bahrain_International_Circuit",
    "circuitName": "Bahrain International Circuit",
    "Location": {
        "lat": "26.0325",
        "long": "50.5106",
        "locality": "Sakhir",
        "country": "Bahrain"
    }
}
```

**Key fields:**
- `circuitId` — unique slug (e.g. `"bahrain"`, `"monaco"`)
- `circuitName` — official full name (e.g. `"Bahrain International Circuit"`)
- `Location.locality` — city/region (used for matching against our `tracks.location`)
- `Location.country` — country name

### Response Wrapper

All responses are wrapped in `MRData`:

```json
{
    "MRData": {
        "series": "f1",
        "limit": "100",
        "offset": "0",
        "total": "78",
        "CircuitTable": {
            "Circuits": [...]
        }
    }
}
```

Use `$response->json('MRData.CircuitTable.Circuits', [])` to extract the data.

## Local Model Mapping

| Jolpica Field | Local Model | Local Field | Notes |
|---------------|-------------|-------------|-------|
| `circuitName` | `Track` | `name` | Official circuit name |
| `Location.locality` | `Track` | `location` | City/region for matching |
| `Location.country` | `Track` | `country` | Country name |

## Location Matching Caveats

Jolpica's `locality` doesn't always match our `tracks.location` exactly. Known overrides (handled in `BackfillTrackNames` command):

| Our `location` | Jolpica `locality` |
|---|---|
| `Spa-Francorchamps` | `Spa` |
| `Yas Island` | `Abu Dhabi` |
| `Monaco` | `Monte Carlo` |

Use `iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', ...)` for accent normalisation when matching.

## Existing Commands

- **`php artisan jolpica:backfill-track-names`** — Updates `tracks.name` to the official circuit name. Supports `--dry-run`. See `app/Console/Commands/BackfillTrackNames.php`.
