# API plan

Target: **one endpoint per data shape**, not one per screen. Where two callers want the
same shape at a different scope, the scope is a parameter — never a second route. Where a
caller wants the same shape with an extra join, the join is an `include`, not a second
route.

Base: `/api/v1`. JSON, UTC ISO-8601 timestamps, durations as **integer seconds**,
distances as **kilometres (double)**, money never typed as a number (see §7.4 of the data
doc — `dealValue` / `paymentReceived` are free text as the seller typed them).

---

## 1. Conventions

### 1.1 Envelope

```jsonc
{
  "data": {},                  // or []
  "meta": { "total": 128, "nextCursor": "eyJ...", "serverTime": "..." },
  "error": null
}
```

Errors: `{"data": null, "error": {"code": "REPORT_NOT_FOUND", "message": "...", "field": null}}`
with a matching HTTP status. `code` is stable and machine-readable; `message` is never
parsed by the client.

### 1.2 The three parameters that collapse the route table

| Param | Values | Meaning |
| --- | --- | --- |
| `subject` | `me` · `<employeeId>` · `team` | Whose data. `me` resolves from the token, so the employee app never sends an id. `team` is admin-only |
| `include` | comma list, per-endpoint | Opt-in joins and sub-collections. Default is the smallest useful payload |
| `range` / `from` / `to` / `date` / `month` | | Time scope. `range` accepts `thisWeek thisMonth lastMonth custom`; `custom` requires `from`+`to` |

`subject` is authorised, not trusted: an employee token may only ever resolve `me`.
Passing `subject=team` with an employee token is `403`, not an empty list.

### 1.3 Pagination

Cursor-based on every unbounded collection (`reports`, `notifications`, `locations`,
`employees`). `?limit=` (default 50, max 200) + `?cursor=`. `limit=0` returns
`meta.total` with an empty `data` — that is how badge counts are fetched.

### 1.4 Caching and delta sync

- `ETag` + `If-None-Match` on every GET. `304` on the catalogue, config, companies and
  closed historical days is where most of the traffic saving lives.
- Historical days are immutable once `endTime` is set → `Cache-Control: private, max-age=86400`.
- Today's day view and the dashboard are `no-store`.
- `?updatedSince=` on `products`, `companies`, `notifications` for incremental pulls.

### 1.5 Idempotency

Every POST that can be retried from an offline queue takes an `Idempotency-Key` header
carrying the device-generated `clientId`. The server returns the **original** result for a
repeated key. Applies to: report submit, session open/close, location batch.

### 1.6 Realtime

The admin dashboard and roster are the only true live surfaces. Serve them over
`GET /api/v1/stream` (SSE) with event types `member.status`, `member.fix`, `report.filed`,
`alert.longStop`. Polling `GET /employees?include=liveStatus` stays the fallback and the
cold-start path — the SSE payloads are the same objects, so there is no second model.

**`/stream` has no client at all — stated 2026-11-04.** `StreamController` is built and
routed, but nothing consumes it. The live map is **Flutter-admin only**: there is no PHP/web
map page and none planned (`04-ADMIN-PANEL-SPEC.md` stands as written and the PHP admin
panel keeps **no** tracking surface). The Flutter admin instead polls
`GET /employees?include=liveStatus,summary` every **15 s** — shipped 2026-11-04,
`mobile_app/lib/widgets/auto_refresh.dart` (`autoRefreshInterval = Duration(seconds: 15)`)
driving `HttpAdminRepository.overview()`. No Dart SSE client was written and none is queued.

So treat `/stream` as **uninvested-in**. Keep its payloads field-for-field consistent with
the polling shapes below — that is free, because `member.status` is already emitted straight
out of `Engine::liveStatus()` — but spend no effort on it, and never let a capability exist
only there. Anything an admin must see has to arrive through
`GET /employees?include=liveStatus` or it does not arrive. The open single-threaded-SAPI
blocker on `/stream` consequently stays open and unprioritised: with no client, it blocks
nothing.

### 1.7 Authorisation matrix

| Role | Reads | Writes |
| --- | --- | --- |
| Employee | `subject=me` only; catalogue where `active=true`; companies; config (read-only) | own sessions, own fixes, own reports, own preferences |
| Admin | any `subject`; full catalogue incl. delisted | employees, products, report reviews, config |

---

## 2. Bootstrap — kill four cold-start round trips

```
GET /api/v1/bootstrap
```

```jsonc
{
  "principal": { "type": "employee", "...": "Employee or AdminUser" },
  "config":       { "...": "TrackingConfig", "version": 9 },   // §3.12 — 7 → 9, v8 folded in
  "preferences":  { "...": "device preferences" },
  "counters":     { "unreadNotifications": 3, "pendingReviews": 11 },
  "serverTime":   "2026-08-18T09:12:44Z"
}
```

Replaces `profile()` + `config()` + notification badge + `pendingReviewCount()` at launch.
Both shells call it; `principal.type` decides which one boots.

---

## 3. Endpoints

### 3.1 Auth

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/auth/login` | `{email, password, device:{id, platform, appVersion, pushToken}}` → `{accessToken, refreshToken, expiresIn, principal}` |
| `POST` | `/auth/refresh` | |
| `POST` | `/auth/logout` | Revokes the device's push token too |
| `PUT` | `/me/device` | Re-register a rotated push token |

One login route for both shells. The role comes back in `principal.type`; the client does
not pick it. (Today's role-select screen is a UI affordance only.)

**Passwords** (built 2026-08-27):

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/me/password` | `{currentPassword, newPassword, refreshToken?}`. **403** (not 401) on wrong current password — a 401 would trigger the client's refresh-and-replay. The caller's own `refreshToken` is spared; every other device is signed out |
| `POST` | `/employees/{id}/password` | admin reset. Optional `password`, else generated; revokes all refresh tokens for that account |

`POST /employees` takes `password` optionally; omitted → the server generates one and
returns `temporaryPassword` on that response only. `principal.mustChangePassword` (on `/me`
and the login payload) is true exactly while the account is on a generated password —
advisory, nothing gates on it. There is **no email forgot-password flow**; admin reset is
the only recovery path.

### 3.2 Principal and preferences

| Method | Path | Returns |
| --- | --- | --- |
| `GET` | `/me` | `Employee` or `AdminUser`, discriminated by `type` |
| `PATCH` | `/me` | Self-editable fields only (avatar, phone) |
| `GET` | `/me/preferences` | §11 of the data doc |
| `PATCH` | `/me/preferences` | Partial |

### 3.3 Employees — **one route serves roster, dashboard, and member-by-id**

```
GET /employees?query=&status=&active=&date=&include=liveStatus,summary&limit=&cursor=
GET /employees/{id}?date=&include=liveStatus,summary
POST /employees
PATCH /employees/{id}
```

- Bare `GET /employees` → `Employee[]` (the roster).
- `include=liveStatus,summary&date=today` → `TeamMember[]`, and `meta.totals` carries the
  whole `TeamOverview` aggregate:

```jsonc
"meta": {
  "date": "2026-08-18",
  "totals": {
    "headcount": 12, "presentCount": 9, "absentCount": 3,
    "workingCount": 6, "idleCount": 2, "offlineCount": 1,
    "totalDistanceKm": 214.8, "totalVisits": 31, "totalReports": 24,
    "pendingReviewCount": 11, "longStopCount": 2, "attendancePercent": 75.0
  }
}
```

  So **the admin dashboard is this endpoint**, not a separate `/dashboard`. `query`
  matches name / employeeCode / region / department (all four, unchanged from the mock).
- `GET /employees/{id}?include=liveStatus,summary` is `memberById()` — the n = 1 case.
- `include=liveStatus` also carries **`activity`** — the one derived enum the live map maps
  1:1 to a marker icon. Additive and read-time only; `status`, `movement` and
  `locationHealth` keep their existing names, meanings and values beside it. Defined once in
  §3.14. **Specced 2026-11-04, not built.** It must flow through `Present::teamMember`, not
  only through the SSE `member.status` payload, because the Flutter admin polls *this*
  endpoint every 15 s and has no SSE client (§1.6).
- `PATCH /employees/{id}` covers both the edit form and the active toggle
  (`{"active": false}`), so `setEmployeeActive` is not its own route.

`department` and `region` accept `""` and are omitted from the request when untouched.

### 3.4 Days — **one route serves employee Home and the admin day view**

```
GET /days/{subject}?date=&include=sessions,stops,visits,reports,activity,summary,lastFix,sync,attendance
```

| Caller | Call | Yields |
| --- | --- | --- |
| Employee Home | `/days/me?include=sessions,summary,activity,visits,stops,lastFix,sync` | `HomeSnapshot` |
| Admin employee detail | `/days/{id}?date=…&include=…,reports,attendance` | `EmployeeDay` |
| Employee activity tab | `/days/me?date=…&include=activity` | `ActivityEvent[]` |

`status`, `movement` and `locationHealth` are always present — they are the payload's
reason for existing. `sync` is only meaningful for `me` and is omitted for an admin
subject (the admin sees queue depth via `RouteTrack.queuedCount` instead).

**`activity` — specced 2026-11-04, not built.** The day view carries the same derived
`activity` enum as `liveStatus` (§3.14), *alongside* and never instead of `status` /
`movement` / `locationHealth`. It is a pure read-time function of state the response already
contains, so it needs no include flag of its own: whatever includes produce the live fields
produce `activity` too. On a past date it resolves from that day's stored state by the same
rules, which is why a settled day reads `ended` rather than `offline`.

`dayState` (`open | closedByEmployee | closedBySystem`) is **always present**, not an
include — `Wire.homeSnapshot` defaults a missing value to `open`, so making it opt-in
would read a locked day as workable. `?include=closeout` adds the `DayCloseout` object.

This replaces `home()`, `activity()` and `employeeDay()`.

**Day closeout and reopen** (built 2026-08-27):

```
POST /days/{subject}/closeout   {clientId, endedAt, declaredDistanceKm, declaredVisits,
                                 rating, tags[], feedback}  → DayCloseout (at data root)
                                 Idempotency-Key: clientId
                                 409 DAY_ALREADY_CLOSED when a closeout with
                                 reopened_at IS NULL already exists
                                 403 unless subject resolves to self
POST /days/{id}/reopen          {date, reason}  → {dayState:"open", date}
                                 admin only; reason mandatory; 404 DAY_NOT_CLOSED
```

`tags[]` ∈ `traffic | vehicleIssue | customerUnavailable | weather | noLeads | goodDay`.
Declared figures sit beside the GPS-measured ones and never overwrite `attendance_days` —
the gap is the signal. `POST /tracking/sessions` returns **409 `DAY_CLOSED`** (after the
`clientId` replay check) when the day is locked. Midnight force-close is the
`php vayu days:close` console command reading each org's own timezone — **not scheduled
on prod yet**, so `closedBySystem` is not emitted until the cron is installed.

### 3.5 Attendance — **one route serves both calendars and the team matrix**

```
GET /attendance?subject=me|{id}|team&month=&from=&to=&format=list|grid
```

| Caller | Call | Yields |
| --- | --- | --- |
| Employee calendar | `?subject=me&month=2026-08` | `Attendance[]` |
| Employee day tap | `?subject=me&from=2026-08-18&to=2026-08-18` | `Attendance[]` of length ≤ 1 (`attendanceForDate`) |
| Admin employee calendar | `?subject={id}&month=…` | `Attendance[]` |
| Admin attendance matrix | `?subject=team&month=…&format=grid` | `TeamAttendanceGrid` |

`format=grid` pivots server-side and returns `byDayKey` maps keyed `yyyymmdd`, exactly as
the matrix consumes them — the client does no date arithmetic.

Each `Attendance` embeds a `summary` object of the nine `DaySummary` fields rather than
repeating them at the top level, which is the §12 de-duplication made concrete.

### 3.6 Statistics — **one route, two scopes**

```
GET /statistics?subject=me|{id}|team&range=&from=&to=&include=perEmployee,daily
```

- `subject=me|{id}` → `PeriodStatistics`
- `subject=team&include=perEmployee` → `TeamStatistics` (`perEmployee` = `EmployeeMetric[]`)

Same field names in both, which is already true of the models. `include=daily` adds the
`dailyDistance` `DailyMetric[]` series; the chart is the only caller that needs it, so it
is opt-in.

### 3.7 Routes — **one route, single track is n = 1**

```
GET /routes?date=&subject=me|{id}|team&employeeIds=a,b,c&include=stops,visits,sessions&simplify=&snap=0|1
```

Always returns `RouteTrack[]`. `subject={id}` returns one element; `subject=team` returns
one per active employee, **empty tracks included** so the map legend can still list who
did not work. Replaces `route()` + `teamRoutes()`.

Each `RouteTrack` carries a **`matched`** object beside the raw `points` (built
2026-08-21): the day's trace resolved onto the road network, encoded as polyline6, one
`segments` entry per work session (sessions are never joined across a break). `matched` is
`null` for today's still-open day, when `snap=0`, when `MATCH_ENABLED=false`, and whenever
the matcher was unavailable — all four mean "draw the raw trace". Geometry is cached per
employee-day in `route_geometry` (first single-employee read fills a miss; `subject=team`
serves cache-only; `php vayu match` backfills). `points` stays authoritative; both are
sent so an admin tells "rode down a side street" apart from "the matcher guessed".

`simplify=` (metres, Douglas–Peucker) is the one real payload lever here — a 1 000-point
day compresses to ~150 points with no visible change to the polyline. Send
`simplify=8` for the overview and `simplify=0` when the user zooms in.
`points` are delivered as a flat `[lat, lng, t, acc, spd, sessionIdx, heading]` tuple
array, not objects — that is roughly a 4× size reduction on the largest payload in the
system.

**Slot 6 — `heading` (specced 2026-11-04, not built).** Degrees clockwise from true north,
`0`–`359.9`, or **`null` when unknown**. See §3.11 for why unknown is `null` and not `0`.
The slot is **optional and purely additive**: a 6-element tuple is still valid and still
means exactly what it meant before, so every existing reader indexing slots 0–5 is
unaffected. A consumer of slot 6 must treat "absent" and "present but `null`" as the same
thing. This is what makes a direction-of-travel vehicle icon possible on the admin map;
nothing stores heading today.

**Slot 5 differs by direction, and always has.** On this read path slot 5 is `sessionIdx` —
the session `seq`; `Engine.php` builds `[$lat, $lng, $t, $acc, $spd, (int) $row['seq']]`. On
the §3.11 **write** path slot 5 is the device `clientId`, because the accepted/rejected
response has to name each fix (`TrackingController::normaliseFix()` reads `$fix[5]` as the
id). The two tuples share a *layout*, not a *field list*. Do not "unify" them — the
asymmetry is load-bearing on both sides. `heading` is slot 6 on both, and it is the only new
slot on either.

### 3.8 Reports — **one route serves three lists**

```
GET  /reports?subject=me|{id}|all&date=&from=&to=&status=&decision=&company=&query=
             &include=employee,review,sales,images&limit=&cursor=
GET  /reports/{id}?include=employee,review,sales,images
POST /reports                       (Idempotency-Key)
POST /reports/{id}/images           (multipart, or exchange for presigned PUTs)
POST /reports/{id}/review           (admin)
```

| Caller | Call |
| --- | --- |
| Employee "today" | `?subject=me&date=today` |
| Employee history | `?subject=me` (newest first, cursor-paged) |
| Admin employee reports | `?subject={id}` |
| Admin review inbox | `?subject=all&decision=pending&include=employee,review` |
| Nav badge | `?subject=all&decision=pending&limit=0` → `meta.total` |

That last line replaces `pendingReviewCount()`. `company=` filters on the free-text
`companyName`; the filter chips are built from `meta.facets.companies` returned alongside,
because with an open text field there is no master list to enumerate.

`POST /reports/{id}/review` returns the `ReportReview` and flips the report's `status` to
`reviewed` in the same response, so the client never re-reads.

#### `?include=images` — the read path, shipped and deployed 2026-11-05

Built in `7c0de0b`, live on production since the 2026-11-05T06:32Z deploy (run 41981030777),
with the client half published in mobile **0.1.7+8** at 06:40:11Z the same day. It closes
the gap where attachments uploaded fine and could never be read back
(`ReportsController::hydrate()` used to select only `COUNT(*)`). Additive on both consumers:
a pre-0.1.7 app and the website are unaffected.

`images` is an array of `Present::image` objects — `id`, `reportId`, `url`, `thumbnailUrl`,
`width`, `height`, `bytes`, `capturedAt`, `latitude?`, `longitude?` — ordered by `position`,
not by insertion. The presenter already existed for the upload response and is reused
verbatim; there is no second shape.

**Absent ≠ empty, and they must stay distinguishable:**

| payload | means |
| --- | --- |
| `images` key **absent** | the caller did not ask |
| `images: []` | asked, and this report has no photos |

`Present::report` omits the key entirely rather than emitting `null`, and the client decodes
with `containsKey`. Collapsing absent to `[]` would make every list row look like a report
with no photos — the same bug in a new costume.

`imageCount` is **unchanged, always sent, and keeps its meaning**, so no caller has to opt in.
The two can disagree for a moment when an upload lands between a list read and a detail read;
the client prefers the array where it has one (`VisitReport.visibleImageCount`) so it never
draws more slots than it has URLs for. List calls deliberately do not ask for the include —
a card only needs the count.

Image `url`/`thumbnailUrl` are document-root relative (`storage/uploads/…`) like every other
image URL here, and resolve against the **API origin**, not `baseUrl`. Resolving against
`baseUrl` yields `/api/v1/storage/…` — a 404 that reads as a missing file rather than a wrong
path. `thumbnailUrl` is plumbed end to end but **nothing generates thumbnails**, so it is
`null` on every row today and clients load full images.

**Attachment files are served unauthenticated, and that is a property of the contract, not an
oversight to discover later.** `.htaccess:10` denies only `storage/apk`, and the
front-controller rewrite at `:11-13` is `!-f`/`!-d` guarded, so an existing file under
`storage/uploads/` is served by Apache and never reaches `index.php` or any token check.
`storage/uploads/reports/<report-uuid>/<image-uuid>.<ext>` is therefore an **unguessable
capability URL**: both segments are UUIDv4 (`ReportsController.php:167`, `:195-196`), so the
tree is not enumerable, but anything holding a URL can fetch it without credentials. These are
customer-site photographs and this presenter also exposes their `latitude`/`longitude`, so
whether that stays acceptable — versus signed URLs or an authenticated streaming route — is an
open decision on the board, not a settled one. Verified reachable 2026-11-05: docroot is
`website/`, `/storage/` → 403 (listing off, not a subtree deny), a nonexistent upload path →
the router's own 404. A genuinely uploaded file has not yet been fetched back over HTTP.

#### `saleLine.imageUrl` — unconditional, resolved at read time

`Present::saleLine` gained `imageUrl`: the catalogue photograph for that product **and**
colour, relative to the API origin, `null` when the catalogue has no photo (the client then
falls back to its painted `ProductArtwork`). Not behind an include — it is one batched query
for the whole page and the admin needs it wherever a sale line is drawn.

Resolution order: first image of that colour by `position` → the product's `hero_image` → null.
Colour is matched by **name**, which is exact rather than a guess: `product_colors` carries
`UNIQUE (product_id, name)` and `color_name` was copied from it at sale time.

**This is a deliberate, recorded exception to standing rule 3** ("what the seller typed is
what gets stored"). `productName` / `colorName` / `colorArgb` stay frozen at sale time so a
renamed product cannot rewrite history. `imageUrl` is **not denormalised** — it is resolved at
read time, so **re-shooting a colour's photo changes the photo shown on older reports**. That
is intended: a product photo is not history, storing the URL at sale time would pin a report to
a file that may later be replaced or deleted, and the employee-side sale card already read the
live catalogue — this makes the admin's view agree with it instead of inventing a third
behaviour.

No N+1: `hydrate()` does a fixed number of queries whatever the row count, pinned by a counting
PDO in `website/tests/report-images.php` (7 queries for 2 reports / 6 sale lines / 4
attachments).

### 3.9 Catalogue — **one route, role decides visibility**

```
GET   /products?category=&query=&includeDelisted=&updatedSince=&limit=&cursor=
GET   /products/{id}
POST  /products                     (admin)
PATCH /products/{id}                (admin)
```

- Employee token: `active=true` products only; `includeDelisted` is ignored (not an error —
  it is a no-op for a role that could never see them).
- Admin token: `includeDelisted=true` returns the full list with an `active` flag.

`PATCH /products/{id}` covers both the edit form and the listed/delisted switch, so
`setProductActive()` and `delistedProductIds()` both disappear — `active` is a field on the
product, not a side-channel set. Delisting never deletes: reports that already reference a
product keep reading correctly because `ProductSaleLine` denormalises the name.

Creating or updating a product fans out an `AppNotification` to the field team — that is
the point of the screen, so it is a server-side side effect, not a second client call.

### 3.10 Companies

```
GET /companies?query=&updatedSince=&include=branches
```

Reference data for visit detection. Small, changes rarely, `ETag` + `updatedSince` make it
effectively free after the first pull.

### 3.11 Tracking write path — **new, and the highest-volume surface**

```
POST  /tracking/sessions              {clientId, startedAt, fix}          → WorkSession
PATCH /tracking/sessions/{id}         {endedAt, fix, reason: pause|end}   → WorkSession
POST  /tracking/locations             {sessionId, fixes:[...]}            → accepted/rejected
POST  /tracking/health                {locationHealth, permission, isOnline}
POST  /tracking/watch                 {employeeIds:[...], ttlSeconds?}    → watch deadlines
                                      admin only — specced 2026-11-04, not built
```

- **`POST /tracking/locations` is the only batched endpoint.** Body carries up to
  `config.syncBatchSize` fixes (default 50) as the same flat tuple array §3.7 uses.
  Response: `{"accepted": ["id"...], "rejected": [{"id":"…","reason":"JUMP"|"DUPLICATE"|"MALFORMED"}]}`
  so the device can drop them from its queue with certainty.
- Accuracy is **never** a rejection reason. A low-accuracy fix (`accuracy_m >
  minAccuracyMetres`) is **stored like any other** — a weak-GPS stretch must leave a trail,
  not a gap — and low-accuracy is classified at read time by comparing the stored
  `accuracy_m` against `config.min_accuracy_metres`, so an admin retuning the threshold
  reclassifies existing fixes without any rewrite. The server rejects only on `JUMP`
  (`maxJumpKmh`), `DUPLICATE` (`UNIQUE (employee_id, client_id)`) and `MALFORMED`
  (missing id/latitude/longitude). The device may still filter on `minAccuracyMetres`
  before upload as an optimisation, never as the guarantee.
- Sessions carry a `clientId` because they are opened offline. A repeated open with the
  same `clientId` returns the existing session — that is what makes "Start Day" safe to
  retry on a dead network.
- Stops and visits are **derived server-side** from the fix chain. The client never posts
  them; posting them would let two devices disagree about what a stop is.
- `POST /tracking/health` is what turns a `TeamMember` red on the admin dashboard while the
  employee's GPS is off. Cheap, fire-and-forget, no response body needed.

#### Fix wire shape — `heading`, additive only (specced 2026-11-04, not built)

The flat tuple grows exactly one slot:

```
[lat, lng, t, acc, spd, clientId, heading]
   0    1   2    3    4      5        6      <- slot 6 is new, optional, nullable
```

- The object form gains **`headingDeg`** under the same rules.
- `Present::fix` returns **`headingDeg: float|null`** (it returns no heading at all today).
- **Old clients must keep working unchanged.** A 6-slot tuple is a complete, valid fix and
  means precisely what it meant before. No version negotiation, no new endpoint, no
  deprecation: `TrackingController::normaliseFix()` already accepts `count($fix) >= 3` and
  resolves absent slots to defaults, so slot 6 is a widening the existing parser tolerates
  by construction. Server-side the only change is reading `$fix[6] ?? $fix['headingDeg'] ??
  null` and passing it through. Do not make slot 6 required on any path, ever.

**`heading_deg` is nullable, and `null` is not `0`.** `0` means **due north**. A stationary
device has no heading; Android reports `-1` and iOS can report `NaN` when it has none; a
synthetic heartbeat fix has none either. All of those are **unknown**, and all of them must
store and transmit `null`. Coalescing unknown to `0` would swing a direction-of-travel
marker to point north on every parked fix, and the client cannot tell that apart from a
genuine northward heading — it would render missing data as data. The validation is
therefore: accept `0 <= heading < 360` and store it; store **`null`** for absent, `null`,
negative, `NaN`, or `>= 360`. Never substitute a default. Storage is
`heading_deg real NULL` — `03-DATABASE-SCHEMA.md` §5.

#### Live watch — pull-on-batch (specced 2026-11-04, not built)

A watch makes one device drain faster while an admin is actually looking at it. There is
**no push dependency**: no FCM, no silent notification, no second transport. The device
learns it is being watched in the reply to a batch it was going to send anyway.

```
POST /tracking/watch          admin only
  {"employeeIds": ["<uuid>", "<uuid>", …], "ttlSeconds": 120}      ttlSeconds optional
```

`employeeIds` is a **list**, deliberately: opening a map of eight people is **one** call, not
eight. Response, standard envelope:

```jsonc
{
  "data": {
    "ttlSeconds": 120,
    "watchedUntil": "2026-11-04T18:57:30Z",   // server now + ttlSeconds; the deadline for every id below
    "watching": [
      {"employeeId": "e1", "watchingUntil": "2026-11-04T18:57:30Z", "extended": true},
      {"employeeId": "e2", "watchingUntil": "2026-11-04T18:57:30Z", "extended": false}
    ],
    "rejected": [
      {"employeeId": "e9", "reason": "NOT_FOUND"}   // NOT_FOUND | NOT_IN_ORG | INACTIVE
    ]
  },
  "meta": {"requestedCount": 3, "watchingCount": 2}
}
```

- `ttlSeconds` **defaults to 120** and **clamps** to `30..600`. Clamp, not `422`: an admin's
  map must never fail to open over a slider value. A malformed (non-integer) `ttlSeconds` is
  `422 VALIDATION_FAILED` on the field; an out-of-range integer is silently clamped and the
  response's `ttlSeconds` tells the caller what it actually got.
- An empty or missing `employeeIds`, or a non-list, is `422 VALIDATION_FAILED` on
  `employeeIds`. A list longer than 200 is `422` (the roster cap `GET /employees` already
  uses). Unknown ids do **not** fail the call — they come back in `rejected`, so one stale
  id cannot stop a map of eight from going live.
- `watchingUntil` / `watchedUntil` are **absolute ISO-8601 UTC**, never durations. The device
  compares them to its own clock minutes after they were issued, so a relative value would
  be wrong by exactly the latency this whole mechanism is about.
- `extended` is `true` when that employee was already being watched and the deadline moved
  later. The watch row is **per employee, not per admin** (`03` §4a), so two admins watching
  the same employee extend one shared deadline rather than each holding a private TTL.
  Last writer wins and the deadline only ever moves forward within an active window.
- **Re-POST to keep a watch alive.** The map should re-watch at roughly `ttlSeconds / 2`
  (60 s at the default) so one dropped call does not drop out of live mode. There is no
  unwatch call: closing the map stops re-posting and the TTL expires on its own. An explicit
  unwatch would need the device to be told, which is exactly the thing we cannot do.
- A watch **never** changes what is stored, derived, or returned about any fix. It changes
  one thing: how often the device talks. Board rule 2 holds unchanged.

**`POST /tracking/locations` carries the deadline back.**

```jsonc
{
  "data": {"accepted": ["c1","c2"], "rejected": [{"id":"c3","reason":"JUMP"}]},
  "meta": {"sessionId": "s1", "liveUntil": "2026-11-04T18:57:30Z"}
}
```

**`liveUntil` goes in `meta`, not in `data` — decided, not open.** `data` is the per-batch
verdict, the two lists the device walks to clear its queue; its `{accepted, rejected}` shape
stays byte-identical so that path is untouched by this feature. `liveUntil` is a statement
about the *device*, not about the batch, and `meta` on this response already carries
`sessionId`, so it is a sibling of something exactly like it rather than a new envelope.
`liveUntil` is `null` when nobody is watching; an older server omits it entirely, and a
client must treat absent and `null` identically.

**Device behaviour — this and nothing else:**

- While `now < liveUntil`, the drain timer runs at `config.liveSyncIntervalSeconds`
  (default **15 s**) instead of `config.syncIntervalSeconds` (default **300 s**).
- When `liveUntil` passes, or comes back `null`/absent, the timer returns to
  `syncIntervalSeconds`. No ramp, no hysteresis — the TTL is the hysteresis.
- **GPS capture cadence is untouched.** `locationIntervalSeconds`, `distanceFilterMetres`,
  the adaptive moving/stationary accuracy state machine and the ~5 min heartbeat all stay
  exactly where they are. A watch costs **radio time, not GPS time**: roughly 20× the
  request count for the watched minutes, and not one extra fix.
- Existing early-flush (`_queue.length >= syncBatchSize`) is unchanged and still wins when
  it binds first.

**Latency — stated plainly, because it is the cost of having no push.** The device only finds
out it is being watched **when it next posts a batch**. Entering live mode therefore takes
**up to one full drain interval — 300 s at the default** — floored in practice by the
existing ~5 min heartbeat, which guarantees a post even from a parked, distance-filtered
device. There is no way around that without a push channel, and we chose not to have one.
**Once live, it is genuinely 15 s.**

That has a UI obligation attached, not just a caveat: **the admin map must always show each
marker's real fix age** and must never present a marker as live merely because a watch was
requested. `Fmt.freshness` (`mobile_app/lib/core/utils/formatters.dart`, shipped 2026-11-04)
counts seconds for exactly this reason — a stamp that rounds to "just now" is
indistinguishable from a screen that has stopped updating. A watch is a request for
freshness, not a claim of it.

### 3.12 Configuration — **breaking: `TrackingConfig` version 7 → 9, one bump (v8 folded in)**

> **SHIPPED AND DEPLOYED.** `031_tracking_config_v9.php` applied on production
> (`migrated 031_tracking_config_v9`, deploy-server.yml run **41886059371**,
> **2026-11-04T18:20:03Z**); re-deployed clean since (`nothing to migrate` on runs
> 41886191521, 41952699831 and 41981030777). The client half shipped in mobile **0.1.5+6**
> and is superseded by 0.1.6/0.1.7. The archaeology below is kept because it explains the
> filename and the single bump; the served `version` has **not** been read back off
> production (`GET /config` needs an admin token nobody in the repo holds).

```
GET /config          → TrackingConfig + {version, updatedAt, updatedBy}
PUT /config          → admin only, full object
```

Every field is writable over the API including the six the UI marks read-only — the UI
restriction is a product decision, and tuning `maxJumpKmh` must not need an app release.
`version` lets the device short-circuit: `If-None-Match` → `304` is the normal case.

Propagation: the device re-reads config at login and at each session start. A change is
**read-time only** — stored fixes are never rewritten, and stop / long-stop classification
is re-evaluated against the config in force at read time. Say so in the admin UI (it
already does).

#### Why 9 and not 8 — one bump, not two

**Version 8 never reached production.** It was specced here on 2026-11-03
(`syncIntervalSeconds`, `distanceFilterMetres`) and web-agent did write the code on the same
day — migration `031_tracking_config_v8.php`, `ConfigController::FIELDS`,
`Present::config()`, `Ctx.php` seeding `version = 8`, plus `tests/config-v8.php` — and the
mobile half is written too (`Wire.config` parses both fields with a v7 fallback, `_drain`
runs off `syncIntervalSeconds`). All of it is **committed** (`532ee70`) and passes on
SQLite.

**Deployment: established 2026-11-04 — v8 DID reach production. The bump is 8 → 9.**
Earlier revisions of this section, and the board, said production still served 7. That was
wrong. The evidence:

- `origin/website` (the deploy branch, which is the `website/` tree) is at `f7661de`,
  *"website: sync website/ from main@532ee70"*, committed **2026-11-02T19:26:50Z**, and that
  tree **contains** `database/migrations/031_tracking_config_v8.php`.
- The `deploy-server.yml` workflow ran **2026-11-02T19:27:39Z** — 49 s later — and succeeded.
  It does `git reset --hard origin/website` and then `php vayu db:sync`, which applies
  pending migrations against the production MariaDB.

So 031 applied, production serves **version 8**, and clients **have** seen an 8 — the mobile
client already parses both v8 fields. Nothing in the rest of this section changes: the four
fields, the dead band, the validation table and the one-migration-one-deploy rule all stand.
Only the starting point moves, 7 → 8.

**This is why the v9 migration must NOT reuse the v8 filename.** The migration ledger keys
on filename, and `031_tracking_config_v8.php` is already recorded as applied in production,
so a retarget under the same name would be **silently skipped** and the two new columns
would never land. It ships as `031_tracking_config_v9.php` for exactly that reason, with
every step idempotent and a version floor that handles 7 → 9 and 8 → 9 identically.

Confirm with an authenticated `GET /api/v1/config` → `version` before and after the deploy
anyway; the above is inferred from deploy history, not read off the server. Either way v9 is one migration and one deploy.

Rather than ship 8 and then immediately ship 9, **v8 is folded into v9**: its two fields are
carried over unchanged, the version constant becomes **9** everywhere 8 appears today, and
production goes **7 → 9 in a single breaking bump without ever serving 8**. Everything in
this section lands together or not at all. The practical change for web-agent is small —
retarget the existing, already-written v8 work to `9` and add the two new fields beside it —
but it must be one deploy. Do **not** deploy 031 as-is first.

#### The four fields

| Field | Type | Default | Status | Meaning |
| --- | --- | --- | --- | --- |
| `syncIntervalSeconds` | int | `300` | carried over from the unshipped v8 | How often the client drains its queued fixes to `POST /tracking/locations` (the mobile `_drain` batch timer). **Independent of `locationIntervalSeconds`** — that is GPS *capture* cadence, this is *upload* cadence. Two separate knobs; moving one must never be assumed to move the other. |
| `distanceFilterMetres` | int | `20` | carried over from the unshipped v8 | GPS distance filter — the OS suppresses fixes until the device moves this far. Already a local field in the mobile `TrackingConfig`; v9 makes it server-driven so an admin can widen it to save battery on slow routes. |
| `movementExitSpeedKmh` | double | `3` | **NEW in v9** | Upper edge of the movement **dead band**. Enter `moving` at `>= movementExitSpeedKmh`; enter `stationary` at `< movementSpeedThresholdKmh`; **hold the current state in between.** |
| `liveSyncIntervalSeconds` | int | `15` | **NEW in v9** | Drain cadence a device uses while an admin is actively watching it (§3.11 live watch). Applies only while `now < liveUntil`; `syncIntervalSeconds` applies otherwise. |

All four are plain scalar fields on the existing `TrackingConfig` object — same `ETag` /
`If-None-Match` / `304` path as every other field, no new endpoint, no new envelope.

#### The movement dead band — what `movementExitSpeedKmh` actually fixes

Today movement is classified off **one fix against one threshold with no hysteresis**:

```php
// website/api/support/Engine.php — liveStatus(), ~line 770
$movement = (float) $lastFix['speed_kmh'] >= (float) $config['movement_speed_threshold_kmh']
    ? 'moving'
    : 'stationary';
```

One noisy fix either side of 2 km/h therefore flips the marker, and the next fix flips it
back. GPS speed noise at walking pace is comfortably ±1 km/h, so this flickers in normal use.

v9 replaces the single comparison with a two-threshold dead band over the **previous**
state:

```
speed >= movementExitSpeedKmh   (3)    → moving
speed <  movementSpeedThresholdKmh (2) → stationary
otherwise                              → unchanged (hold whatever it was)
no previous state and in the band      → stationary   (the cold-start default)
```

`movementSpeedThresholdKmh` keeps its existing name, default (2) and meaning — the lower
edge. Nothing about it changes, so an admin who already tuned it is not surprised. The
device-side equivalent already exists and is already debounced (`_evaluateMovement`, 90 s
hold window, `mobile_app/lib/services/location_service.dart`); v9 is what lets the server's
answer stop disagreeing with the phone's.

#### Validation — clamp or 422, stated per rule

| Rule | On violation | Why |
| --- | --- | --- |
| `syncIntervalSeconds <= offlineThresholdMinutes * 60 / 2` | **`422 VALIDATION_FAILED`**, `field: "syncIntervalSeconds"` | Carried over from the v8 spec and already implemented (`ConfigController`). A device must post at least twice inside the offline window or it is flagged offline *between its own uploads*. At the default `offlineThresholdMinutes = 10` the ceiling is 300 s, which is the default. If `offlineThresholdMinutes` moves on the same write, **validate the pair together** against the post-write values. Keep the DB `CHECK (sync_interval_seconds <= offline_threshold_minutes * 30)` as the backstop. |
| `movementExitSpeedKmh >= movementSpeedThresholdKmh` | **`422 VALIDATION_FAILED`**, `field: "movementExitSpeedKmh"` | An inverted band is not a band — it would make both predicates true at once and the "hold" case unreachable. Equality is legal and simply means "no dead band", i.e. today's behaviour. Validate the pair together when either side moves. Mirrors the existing `long_stop_threshold_minutes >= stop_threshold_minutes` discipline. |
| `liveSyncIntervalSeconds < syncIntervalSeconds` | **clamp** to `syncIntervalSeconds - 1`, no error | A live interval at or above the normal one makes "live" meaningless but breaks nothing and loses no data, so it must not fail an admin's save. Clamp silently and return the corrected value — the response is the acknowledgement. |
| `liveSyncIntervalSeconds` range | **clamp** to `5..120` | Below 5 s is a battery and rate-limit hazard; above 120 s is not live. Same reasoning: clamp, do not reject. |
| `movementExitSpeedKmh` range | **clamp** to `0..50` | Out-of-range here is a typo, not an intent. |

Non-numeric input on any of the four is `422 VALIDATION_FAILED` on that field — clamping
applies to numbers out of range, never to garbage.

#### What web-agent must touch for v9

- `GET /config` serves all four fields at `version: 9`.
- `PUT /config` accepts all four on the admin **"tracking rules"** update path
  (`ConfigController::FIELDS`), with the validation above.
- Storage columns and the `tracking_config_history` mirror: `03-DATABASE-SCHEMA.md` §4.
- Seed `version = 9` at **all three** insert sites that currently seed 8 —
  `api/support/Ctx.php` (lazy insert), `database/seeds/001_bootstrap_admin.php`,
  `database/demo/seed.php` — plus the migration-time floor/bump so a device holding a v7
  (or v8) ETag re-pulls.
- `Engine::liveStatus()` uses the dead band (above) and emits `activity` (§3.14).
- **Report before shipping.** Two consumers, breaking shape change.

#### Version skew — both directions, explicitly

| Situation | Behaviour |
| --- | --- |
| **v9 client, v7 server** (today's production) | The server omits all four fields. The client resolves each to its **local default** — 300 / 20 / 3 / 15 — which is what the app already does: `Wire.config` uses `integerOr(raw, fallback)` precisely so a missing key cannot become `0` (a 0 s drain timer). It reads `version: 7`, nothing is null, nothing crashes, and the app behaves exactly as it does now. Same path already proven for `autoCloseAfterMinutes`. No fallback *to* the server is needed: the defaults are the shipped behaviour. |
| **v7 client, v9 server** | The server sends four fields the old client does not parse; unknown keys are ignored, so it keeps its hard-coded 5-min drain and 20 m filter and is unaffected. The one real consequence: **a v7 client cannot be put into live mode.** It ignores `liveUntil` and keeps draining every 300 s, so an admin watching it sees 5-minute-old fixes however many times they re-watch. That is a correctness-preserving degradation, not a break — and it is the reason the admin map must display real fix age (§3.11) rather than assume a watch worked. |
| v8 client | Does not exist. 8 was never deployed (above). |

#### Interactions that already held and still hold

**`syncBatchSize` (default 50).** If `syncIntervalSeconds` is high enough that more than
`syncBatchSize` fixes accumulate between scheduled drains, the client's existing early-flush
(`_queue.length >= syncBatchSize`) fires and uploads immediately. That is **expected, not an
error** — the interval is an upper bound on latency, the batch size a cap on request size,
and whichever binds first wins. The server must accept an early batch exactly like a timed
one. Unchanged by `liveSyncIntervalSeconds`, which only shortens the interval.

**`distanceFilterMetres` and offline detection.** A wide distance filter can suppress fixes
for minutes when an employee is parked, but it **cannot by itself starve the offline
detector**: the mobile client forces a fix into the queue every ~5 min
(`_heartbeatInterval`, `location_service.dart`) regardless of movement. So
`distanceFilterMetres` is safe to raise without touching `offlineThresholdMinutes` /
`locationUnavailableThresholdMinutes`. Do not treat a quiet distance-filtered stretch as
grounds to lower the offline threshold. That same heartbeat is what floors live-mode entry
latency at ~5 min (§3.11).

### 3.13 Notifications

```
GET   /notifications?unreadOnly=&updatedSince=&limit=&cursor=
PATCH /notifications/{id}            {"read": true}
POST  /notifications/read-all
```

Badge count comes from `/bootstrap` at launch and from `limit=0&unreadOnly=true`
afterwards; push delivers the increments.

### 3.14 `activity` — one derived enum the map maps 1:1 to an icon

**Specced 2026-11-04, not built.** Added to **four** payloads, all of them read paths:

| Payload | Where |
| --- | --- |
| `liveStatus` | `Engine::liveStatus()` return, surfaced via `include=liveStatus` (§3.3) |
| `teamMember` | **`Present::teamMember()`** — this is the one the Flutter admin actually polls (§1.6) |
| day view | `GET /days/{subject}` (§3.4) |
| SSE `member.status` | `StreamController` — kept consistent, but it has no client (§1.6) |

```
activity: moving | stopped | idle | location_unavailable | offline | paused | ended | not_started
```

#### It is derived, additive and read-time only

- **Additive.** `status` (`WorkStatus`), `movement` (`MovementStatus`) and `locationHealth`
  (`LocationHealth`) keep their current names, values and meanings, and keep being sent.
  Nothing that reads them today changes. `activity` sits beside them.
- **Derived.** There is no `activity` column, no `activity` enum type in the database, and
  nothing writes it. It is a **pure function** of state that already exists.
- **Read-time only.** Thresholds are applied when the response is built and **never rewrite
  stored fixes** — board rule 2, unchanged. Retuning `offlineThresholdMinutes` reclassifies
  every past and present response on the next read and touches no row.

It exists for one reason: the live map needs **one** value to pick **one** icon. Without it
the client has to combine three enums plus two timestamps plus an open-stop lookup to decide
what to draw, which means that logic gets written twice — once in Dart and once in whatever
reads the API next — and the two copies drift. `activity` collapses it into the response,
once, on the side that owns the thresholds.

#### Inputs it reads

Exactly these, and nothing else:

| Input | Source |
| --- | --- |
| today's sessions for the employee | `work_sessions (employee_id, work_date)` |
| the open session, if any | `open_sessions` partial index (`ended_at IS NULL`) |
| last fix `received_at` and `recorded_at` | `location_fixes`, last row for the day |
| `locationHealth` | `device_health.location_health` |
| open stop, if any | `stop_records` via the `open_stops` partial index (`departure IS NULL`) |
| last fix `speed_kmh` + previous `movement` | for the dead band |
| `dayState` | `day_closeouts` (§3.4) |
| thresholds | `tracking_configs` — `offlineThresholdMinutes`, `locationUnavailableThresholdMinutes`, `movementSpeedThresholdKmh`, `movementExitSpeedKmh` |

Every one of those is already loaded by `Engine::liveStatus()` or sits one partial-index
lookup away. `activity` adds **no new query** beyond what the open-stop lookup already costs
the dashboard (`openStopDuration` is already presented, so that row is already fetched).

#### Derivation, in precedence order

First match wins. Evaluate top to bottom — the order is the specification, not a hint.

| # | Condition | `activity` |
| --- | --- | --- |
| 1 | **No session today** (no rows for the work date) | `not_started` |
| 2 | Open session **and** `locationHealth` ∈ (`service_disabled`, `permission_denied`, `permission_denied_forever`, `background_denied`) | `location_unavailable` |
| 3 | Open session **and** last fix **`received_at`** older than `offlineThresholdMinutes` | `offline` |
| 4 | Open session **and** last fix **`recorded_at`** older than `locationUnavailableThresholdMinutes` | `location_unavailable` |
| 5 | Open session **and** an **open `stop_records` row** (`departure IS NULL`) | `stopped` |
| 6 | Open session **and** movement is `moving` under the §3.12 dead band | `moving` |
| 7 | Open session **and** stationary, but no open stop yet | `idle` |
| 8 | **No** open session, sessions exist — `dayState = open` | `paused` |
| 8b | **No** open session, sessions exist — `dayState` closed (`closedByEmployee`/`closedBySystem`) | `ended` |

Notes on the order, because each position is a decision:

- **2 before 3.** A device with permission revoked is not "offline" — we know exactly why
  it is silent, and the admin's action is different (call the employee about permissions,
  not about network). A known cause beats an inferred one.
- **3 before 4.** `received_at` (server clock) before `recorded_at` (device clock). If
  nothing has *arrived*, that is `offline` — a network statement. If batches are arriving
  but the fixes inside them are stale, the radio works and the GPS does not, which is
  `location_unavailable`. The two timestamps distinguish a dead network from a dead GPS, and
  that is why both exist on `location_fixes`.
- **5 before 6/7.** An open stop is the authoritative answer about a standing employee, and
  it already carries dwell time.
- **6 and 7 are the same branch.** Both mean "moving around under GPS, not settled at a
  stop"; the dead band is the only thing separating them.
- **8 vs 8b.** Between sessions the day is `paused`; once closed out it is `ended`. The
  existing `dayState` already distinguishes these, so `activity` must not invent a third
  reading. A reopened day goes back to `paused`.

#### Rule 5 gets stop hysteresis for free

Rule 5 reads the **existing** `stop_records` table through the **existing** `open_stops`
partial index. Those rows are only created once a fix cluster has persisted for
`stopThresholdMinutes` within `stopRadiusMetres` — so **dwell time is already encoded in the
row's existence**. Rule 5 therefore inherits stop hysteresis with **no new dwell tracking,
no new timer, no new state**: a brief pause at a traffic light never opens a stop row and so
never reads `stopped`, while a genuine 10-minute halt does. Do not add a parallel dwell
mechanism for `activity`; if the stop rules need tuning, tune `stopThresholdMinutes` and
every consumer moves together.

Together with the §3.12 dead band on rule 6, that gives both halves of the flicker fix:
speed noise is damped by the dead band, and short halts are damped by the stop threshold.

#### Mapping (informative, not contract)

The client owns its icons; this is only here so the enum's purpose is unambiguous.
`moving` → vehicle icon rotated by `headingDeg` (§3.11) when non-null, unrotated when null ·
`stopped` → pin with dwell duration · `idle` → hollow dot · `location_unavailable` → grey
marker with a GPS-off badge · `offline` → grey marker with last-seen age · `paused` → marker
at last fix, muted · `ended` → day-summary marker · `not_started` → not on the map.

---

## 4. What this collapses

| Repository method | Endpoint |
| --- | --- |
| `EmployeeRepository.profile()` | `GET /me` (or `/bootstrap`) |
| `.home()` | `GET /days/me` |
| `.activity({date})` | `GET /days/me?include=activity` |
| `.reports({date})` | `GET /reports?subject=me` |
| `.reportById(id)` | `GET /reports/{id}` |
| `.attendance({month})` | `GET /attendance?subject=me&month=` |
| `.attendanceForDate(date)` | `GET /attendance?subject=me&from=&to=` |
| `.statistics({range,from,to})` | `GET /statistics?subject=me` |
| `.companies()` | `GET /companies` |
| `.products({category,query})` | `GET /products` |
| `.productById(id)` | `GET /products/{id}` |
| `.notifications()` | `GET /notifications` |
| `.markNotificationRead(id)` | `PATCH /notifications/{id}` |
| `.markAllNotificationsRead()` | `POST /notifications/read-all` |
| `.submitReport(draft)` | `POST /reports` |
| `AdminRepository.profile()` | `GET /me` |
| `.overview({date})` | `GET /employees?include=liveStatus,summary` → `meta.totals` |
| `.team({query,status})` | `GET /employees` |
| `.memberById(id)` | `GET /employees/{id}?include=liveStatus,summary` |
| `.employeeDay({id,date})` | `GET /days/{id}` |
| `.route({id,date})` | `GET /routes?subject={id}` |
| `.teamRoutes({date,ids})` | `GET /routes?subject=team` |
| `.employeeAttendance({id,month})` | `GET /attendance?subject={id}` |
| `.employeeReports(id,{date})` | `GET /reports?subject={id}` |
| `.teamAttendance({month})` | `GET /attendance?subject=team&format=grid` |
| `.teamStatistics({range})` | `GET /statistics?subject=team` |
| `.inbox({decision,employeeId,date,query})` | `GET /reports?subject=all&include=employee,review` |
| `.inboxItem(reportId)` | `GET /reports/{id}?include=employee,review` |
| `.reviewReport({id,decision,note})` | `POST /reports/{id}/review` |
| `.pendingReviewCount()` | `GET /reports?decision=pending&limit=0` |
| `.products()` / `.productById()` | `GET /products` (role widens visibility) |
| `.delistedProductIds()` | — folded into `Product.active` |
| `.saveProduct(draft)` | `POST /products` · `PATCH /products/{id}` |
| `.setProductActive(id,active)` | `PATCH /products/{id}` |
| `.saveEmployee(draft)` | `POST /employees` · `PATCH /employees/{id}` |
| `.setEmployeeActive(id,active)` | `PATCH /employees/{id}` |
| `.config()` / `.saveConfig()` | `GET /config` · `PUT /config` |
| *(not in either repository)* | `POST /auth/*`, `POST /tracking/*`, `POST /reports/{id}/images`, `GET /me/preferences`, `GET /bootstrap`, `GET /stream` |

**39 repository methods collapse onto 17 paths.** A further 12 paths cover what no mock
ever needed — auth, the location ingest write path, image upload, preferences, bootstrap
and the SSE stream — for 29 total.

The four heaviest screens each render from a single request: Home is `GET /days/me`, the
dashboard is `GET /employees?include=liveStatus,summary`, the review inbox is
`GET /reports?subject=all`, the admin day view is `GET /days/{id}`.

---

## 5. Repository seam — what actually changes in the app

`EmployeeRepository` and `AdminRepository` stay exactly as they are. An HTTP
implementation maps method → route using the table above; no widget changes, which is the
whole point of the seam. Two notes for whoever writes it:

1. `delistedProductIds()` becomes `products.where(!active).map(id).toSet()` computed in the
   HTTP repository, so the interface does not have to change on day one. Drop the method in
   a later pass.
2. `attendanceForDate()` should hit the same cached month the calendar already pulled
   rather than issuing a request — it is a lookup, not a fetch.
