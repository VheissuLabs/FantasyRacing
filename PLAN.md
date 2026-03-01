# Plan: Draft Scheduling

## Problem
- The `draft_sessions.scheduled_at` column exists but is never populated
- The draft setup form doesn't include a date/time field
- The league show page has no draft awareness — it shows "Create Your Team" regardless of draft state
- Members need to know when to be online for the draft

## Changes

### 1. Backend: Add `scheduled_at` to draft setup (`DraftController@setup`)
- Accept `scheduled_at` (required, datetime, must be in the future) in the `setup` method
- Validate and store it on the `DraftSession`

### 2. Backend: Add `schedule` endpoint to update draft time (`DraftController@schedule`)
- New `POST /leagues/{league}/draft/schedule` endpoint
- Allows commissioner to update `scheduled_at` on a pending draft session
- Validates datetime is in the future

### 3. Backend: Pass draft session to league show page (`LeagueDirectoryController@show`)
- Load the league's `draftSession` (just `id`, `status`, `scheduled_at`)
- Pass it as a `draftSession` prop so the page knows the draft state

### 4. Frontend: Add datetime picker to draft setup form (`Leagues/Draft/Show.vue`)
- Add a `datetime-local` input for `scheduled_at` to the setup form
- The field is required — commissioner must pick a date/time

### 5. Frontend: Draft-aware league show page (`Leagues/Show.vue`)
- Accept `draftSession` prop
- When member has no team:
  - If no draft session exists: show "Draft not yet scheduled"
  - If draft is pending with `scheduled_at`: show the draft date/time + link to Draft Room
  - If draft is completed: show "Create Your Team" (current behavior)
- When member has a team: keep current behavior (team link + Draft Room button)

### 6. Add route for new schedule endpoint (`routes/web.php`)

### 7. Tests
- Draft setup stores `scheduled_at`
- Draft schedule endpoint updates `scheduled_at`
- League show page passes `draftSession` prop
- League show page returns null `draftSession` when none exists

## Files to Modify
- `app/Http/Controllers/Leagues/DraftController.php`
- `app/Http/Controllers/Leagues/LeagueDirectoryController.php`
- `resources/js/pages/Leagues/Draft/Show.vue`
- `resources/js/pages/Leagues/Show.vue`
- `routes/web.php`
- `tests/Feature/Leagues/DraftTest.php` (new or existing)
