# Fantasy Racing — Remaining Tasks

Cross-referenced against `fantasy-racing-spec.md`. Items marked done are omitted.

---

## Phase 1 — Core Backend

### Filament Admin Actions
- [x] Add "Calculate Points" action button on Event resource (dispatches `CalculateEventPoints` job)
- [x] Add bulk "Calculate All Pending" action on Event resource list page
- [x] Filament scoping — franchise managers should only see/edit their own franchise's data (events, results, drivers, constructors, pitstops)
- [x] Franchise manager assignment UI in Filament (manage `franchise_managers` pivot)

### Notifications & Jobs
- [x] `NotifyTeamsOfEventPoints` — integrated into `CalculateEventPoints` job, sends `EventPointsCalculatedNotification`
- [x] `NotifyDraftStarting` — convert existing console command to a dispatchable job (scheduled 15 min before `draft_sessions.scheduled_at`)
- [x] `ExpireLeagueInvites` — scheduled in `routes/console.php` (hourly)
- [x] `SendLeagueInviteEmail` — verify Mailable/notification template renders correctly

### Broadcast Channels (`routes/channels.php`)
- [x] `draft.{draftSession}` — authorize league members only
- [x] `league.{league}` — authorize league members only
- [x] `event.{event}` — authorize verified users

### League Rules Helpers
- [x] Add helper methods on `League` model (`rule()`, `canTrade()`, `tradeApprovalRequired()`, `noDuplicates()`, `maxRosterSize()`)
- [x] Refactored `TradeService` to use League helper methods

### Trade Lock Enforcement
- [x] `assertNoActiveEventLock()` in `TradeService` — already implemented
- [x] `assertNoDuplicatesPostTrade()` in `TradeService` — already implemented

### Bug Fixes
- [x] `events.locked_at` migration — changed to `nullable()` (was NOT NULL, broke event creation)
- [x] `FantasyTeamRoster` model — added `$table = 'fantasy_team_roster'` (table is singular in migration)
- [x] `FreeAgentPool` model — added `$table = 'free_agent_pool'` (table is singular in migration)
- [x] `LeagueFactory` — fixed `franchise_id` (was null, causing constraint violations in tests)

### Fantasy Team Creation
- [x] `FantasyTeamController::create()` + `store()` methods
- [x] `StoreFantasyTeamRequest` form request (validates membership, prevents duplicates)
- [x] Routes: `GET /leagues/{league:slug}/teams/create`, `POST /leagues/{league:slug}/teams`
- [x] `resources/js/pages/Leagues/Teams/Create.vue` — team name form
- [x] `Leagues/Show.vue` — "Create Your Team" CTA for members without a team, team link + Draft Room button for members with a team

### Draft Commissioner Controls
- [x] `DraftController::setup()` — create draft session (type, pick timer)
- [x] `DraftController::generateOrder()` — generate snake/linear draft order
- [x] `DraftController::start()` — start the draft
- [x] `DraftController::pause()` / `resume()` — pause/resume controls
- [x] Error handling on `DraftController::pick()` — returns validation errors
- [x] `Draft.vue` — commissioner setup form (no session), commissioner controls (generate order, start, pause, resume)
- [x] `Draft.vue` — handles null session state gracefully

---

## Phase 2 — Public Pages

### Driver Profiles
- [x] `DriverProfileController` — `show(Driver $driver)` and `season(Driver $driver, Season $season)`
- [x] Routes: `GET /drivers/{driver:slug}`, `GET /drivers/{driver:slug}/seasons/{season}`
- [x] `resources/js/pages/Drivers/Show.vue` — header (photo, name, number, nationality, constructor), career summary, season-by-season table, event results log, fantasy stats (ownership %, avg points, best haul)

### Constructor Profiles
- [x] `ConstructorProfileController` — `show(Constructor $constructor)` and `season(Constructor $constructor, Season $season)`
- [x] Routes: `GET /constructors/{constructor:slug}`, `GET /constructors/{constructor:slug}/seasons/{season}`
- [x] `resources/js/pages/Constructors/Show.vue` — header, current driver lineup, career summary, season history, event results grouped by event showing both drivers

### League Directory Enhancements
- [x] Filters: franchise, season, availability, join_policy
- [x] Shareable filter URLs (query string persistence)
- [x] League card UI: name, franchise, commissioner, member count, join policy badge

---

## Phase 3 — Commissioner & League Management

### League Settings Page
- [x] `resources/js/pages/Leagues/Settings.vue` — edit name, description, rules, visibility, join_policy, max_teams, invite_code
- [x] Commissioner-only route gated by `LeaguePolicy@manage`

### Trade Approval Queue
- [x] Commissioner view of pending trades (when `trade_approval_required` rule is enabled)
- [x] Approve/reject actions on trade proposals

### Join Request Management
- [x] Commissioner UI for approve/reject join requests with optional rejection message
- [x] Capacity check (`max_teams`) enforced before approval

### Invite Management
- [x] Resend/cancel invite actions for commissioner
- [x] Invite code shareable link generation

---

## Phase 4 — Real-Time Chat

### Database
- [ ] Migration: `chat_messages` table (id, channel_type [league|event], channel_id, user_id, body, flagged_at, flagged_by, deleted_at, timestamps)
- [ ] `ChatMessage` model

### Backend
- [ ] `ChatController` — store, flag, delete (commissioner/admin)
- [ ] `MessageSent` broadcast event
- [ ] Content filtering (word list auto-flag)
- [ ] Flag notification to commissioner/admin

### Frontend
- [ ] `resources/js/pages/Leagues/Chat.vue` — persistent league chat room
- [ ] `resources/js/pages/Events/Chat.vue` — live event chat during qualifying/race
- [ ] Message input, scrollable history, flag button, admin delete

---

## Phase 5 — Draft Room Polish

### Draft UI/UX
- [x] Pre-draft lobby (15 min before start, show countdown)
- [x] Searchable/filterable entity panel (filter drivers by constructor, etc.)
- [x] Live roster panel showing each team's picks grouped by team
- [x] "It's your pick" banner/highlight
- [x] Pick history feed

### Draft Management
- [x] Commissioner view/edit draft order before draft starts
- [x] Randomize draft order (`POST /draft/order/randomise`)
- [x] Pause/resume UI integration (commissioner controls on Draft.vue)

---

## Phase 6 — Testing

### Points Calculation
- [x] Test all driver point breakdown scenarios (position, fastest lap, DOTD, DNF penalty, sprint positions gained/lost, overtakes)
- [x] Test all constructor point breakdown scenarios (position, pitstop brackets, Q-stage bonuses, DSQ penalty, 1-2 finishes)
- [x] Test roster snapshot usage vs live roster fallback

### Draft
- [x] Test draft order generation (snake draft)
- [x] Test pick validation (entity available, correct turn)
- [x] Test auto-pick / full draft completion
- [x] Test pause/resume lifecycle
- [x] Test commissioner setup/start flow

### Trades
- [x] Test trade proposal creation and validation
- [x] Test lock enforcement (entities in active event window)
- [x] Test no-duplicates rule enforcement
- [x] Test commissioner approval flow

### Jobs
- [x] Test `CalculateEventPoints` job dispatches `RefreshSeasonStats`
- [x] Test `RefreshSeasonStats` computes correct stats
- [x] Test `EventObserver` creates roster snapshots on lock

### Integration
- [x] End-to-end: create league, join, draft, lock event, calculate points, view standings
