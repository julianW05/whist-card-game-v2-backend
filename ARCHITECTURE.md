# Whist Backend — Architecture & API Reference

Server-side for the Whist card game (see `../whist-project-briefing.md` for full game rules).
Laravel 13 · PHP 8.5 · Sanctum auth · Reverb (WebSockets) · MySQL (sqlite in tests).

**All game logic is server-side.** The client sends intent (bid this, play this card); the
server validates every action and broadcasts the resulting state. Never trust the client.

---

## Conventions

- **Service classes** own all domain logic. Controllers are skinny: resolve models via
  route-model binding, call a service, return a Resource.
- **API Resources** shape every JSON response. Enums are emitted as their backed `->value`.
- **Form Requests** validate input shape only; game-rule validation lives in the services and
  throws `ValidationException` (→ HTTP 422).
- **Minimal models**: relations + casts + `#[Fillable]`, no business logic.
- All state mutations run inside `DB::transaction`.
- No code comments (project preference); PHPDoc type blocks are fine.
- Run `vendor/bin/pint --dirty --format agent` after editing PHP. Every change needs a test.

---

## Domain model

`User` → `GamePlayer` (seat_index, total_score) → `Game` → `Round` → `Trick` → `TrickCard`.
`Bid` belongs to a round+user. `Card` is the seeded 48-card library (6 values × 4 suits × 2
copies). `GameDeck` is the per-round shuffled deck (status: in_deck / in_hand / discarded).

Enums: `GameStatus` (lobby/active/finished), `RoundStatus` (bidding/playing/complete),
`Suit`, `CardValue`, `DeckCardStatus`. `Suit::forRound()` and `Suit::symbol()` derive trump.

---

## Services (`app/Services/`)

| Service | Responsibility |
|---|---|
| `GameService` | create / join (by code) / leave / kick / start game; 6-char codes; seat assignment + resequencing; public-lobby list. Min 2, max 6 players. |
| `RoundService` | pyramid round numbering, trump rotation, dealer rotation; `startNextRound()` creates the round, deals (except blind final round), fires `RoundStarted`. |
| `DeckService` | `generateForRound` (shuffle 48), `deal`, `getHand`, `playCard`. |
| `BiddingService` | bid order (left of dealer first, dealer last), range 0..trick_count, **hook rule** (dealer can't make total bids == trick_count); on last bid deals the blind final round, opens trick 1, transitions round to `playing`. |
| `TrickService` | follow-suit enforcement, trump/lead-suit winner resolution (ties → first played wins), winner leads next trick; on final trick scores the round and may finish the game. |
| `ScoringService` | round scoring: exact bid → `10 + bid`, wrong → `-1` flat; updates `bids.tricks_won/points_earned` and `game_players.total_score`. Called once by `TrickService` at round end. |
| `GameStateService` | **read-side assembler**. `build(Game, ?User)` returns `['game','round','trick','hand']` with eager-loading. Pass `null` user → empty hand (used for broadcasts). |

Game flow: `startGame` → `RoundService::startNextRound` → bidding (`BiddingService`) →
playing (`TrickService`) → scoring → next round or game complete.

---

## HTTP API (`routes/api.php`)

All routes are under `auth:sanctum`. Mutations return a `GameStateResource` (or `GameResource`
for lobby actions); JSON is wrapped in a top-level `data` key.

| Method | Endpoint | Action | Notes |
|---|---|---|---|
| GET | `/api/games` | `GameController@index` | public lobbies |
| POST | `/api/games` | `store` | body: `name`, `is_public`; 201; host seated |
| POST | `/api/games/{game:code}/join` | `join` | binds by **code** |
| POST | `/api/games/{game}/start` | `start` | host only; returns full state |
| POST | `/api/games/{game}/kick/{user}` | `kick` | host only |
| POST | `/api/games/{game}/leave` | `leave` | lobby only; 204 |
| GET | `/api/games/{game}/state` | `state` | full sync; participants only (403 otherwise) |
| GET | `/api/games/{game}/hand` | `hand` | the auth user's own hand only |
| POST | `/api/rounds/{round}/bid` | `RoundController@bid` | body: `amount` |
| POST | `/api/rounds/{round}/start-next` | `startNext` | host only; round must be complete |
| POST | `/api/tricks/{trick}/play` | `TrickController@play` | body: `game_deck_id` |

`/api/user` (stock) returns the authenticated user.

### Authorization split
- Authentication: `auth:sanctum` middleware.
- Host-only / turn-order / rule checks: in the services → `ValidationException` (422).
- Participant read-access (`state`, `hand`): inline check in `GameController` → 403.
- **Hands are private**: `hand` and the `hand` inside `state` are always built for
  `$request->user()`. A player only ever receives their own cards.

---

## WebSockets (Reverb)

Default broadcaster is `reverb`. Events broadcast on the **presence channel**
`presence-game.{game_id}` (auth in `routes/channels.php`, `sanctum` guard — only participants,
returns `{id, name}` for the roster).

Events (`app/Events/`) extend `GameEvent`, which implements `ShouldBroadcast` +
`ShouldDispatchAfterCommit` (so they only fire after the DB transaction commits — fired from
inside services at the exact domain moment). `broadcastAs()` = class basename.

| Event | Fired from | When |
|---|---|---|
| `GameStarted` | `GameService::startGame` | host starts |
| `RoundStarted` | `RoundService::startNextRound` | every new round |
| `BidPlaced` | `BiddingService::placeBid` | each bid |
| `BiddingComplete` | `BiddingService` | last bid placed |
| `CardPlayed` | `TrickService::playCard` | each card |
| `TrickComplete` | `TrickService` | trick won |
| `RoundComplete` | `TrickService` | final trick scored |
| `GameComplete` | `TrickService` | final round done (game → finished) |
| `PlayerReconnected` | (not auto-wired) | carries reconnecting user; needs a trigger endpoint |

### Broadcast payload
`broadcastWith()` returns `GameStateService::build($game, null)` rendered through
`GameStateResource` — identical shape to the `/state` endpoint's `data`, **but `hand` is always
`[]`** (no private hands on the shared channel). Each client merges its own hand from `/hand`.

State payload shape:
```
{ game, round|null, trick|null, hand: [], scoreboard }
```

---

## Resources (`app/Http/Resources/`)

`GameResource`, `RoundResource` (+ trump symbol), `TrickResource`, `TrickCardResource`,
`BidResource`, `PlayerResource`, `HandResource` (exposes the `game_deck` id needed by `/play`),
`CardResource`, `ScoreboardResource` (per-round rows: bid/actual cells, running cumulative,
per-row `leader_id`), and `GameStateResource` (composes game + round + trick + hand + scoreboard
from the `GameStateService::build` array).

---

## Testing

PHPUnit feature tests, one per service plus `GameApiTest`, `ResourceTest`, `BroadcastTest`.
`BROADCAST_CONNECTION=null` in `phpunit.xml` so tests never hit Reverb; `BroadcastTest` uses
`Event::fake()` to assert dispatch. Run: `php artisan test --compact`.

---

## Not yet built / TODO

- `PlayerReconnected` trigger (e.g. a `POST /api/games/{game}/sync` endpoint on reconnect).
- A `GamePolicy` (host/participant checks are currently inline).
- Reverb credentials in `.env` to run `php artisan reverb:start` locally.
- The Nuxt frontend (`../whist-card-game-v2-frontend`).
