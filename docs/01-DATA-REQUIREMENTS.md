# Data requirements

Complete field-level inventory of everything the two apps read or write.
Derived from the models in `lib/data/models/`, the two repository contracts
(`EmployeeRepository`, `AdminRepository`), `TrackingConfig`, `SettingsController`
and `LocationService`. Nothing the UI renders is omitted — see
[§14 Coverage checklist](#14-coverage-checklist).

**Legend**

- **Req** — `R` required, `O` optional/nullable, `D` derived (computed, never stored or sent)
- **Owner** — who produces the value: `EMP` employee device, `ADM` admin console, `SRV` server, `SYS` device/OS

---

## 0. Actors and ownership rules

| Rule | Consequence |
| --- | --- |
| Employee endpoints take identity from the auth token | Employee-facing payloads carry **no** `employeeId` |
| Admin endpoints address an employee by path/query id | Ownership lives in admin envelopes (`TeamMember`, `EmployeeDay`, `RouteTrack`, `ReportInboxItem`) |
| Location/tracking thresholds are **admin-owned config**, not per-device settings | One `TrackingConfig` per organisation; the device fetches it, never invents it |
| A threshold change is **read-time only** | Already-stored GPS fixes are never rewritten; stop/long-stop classification is re-evaluated on read |
| Company and branch on a report are **free text** | `companyId`/`branchId` are an optional link, set only when the report was filed against a detected visit |
| No price anywhere in the catalogue | Sellers log units + payment received only |

---

## 1. Identity

### 1.1 Employee — `Employee`

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string (uuid) | R | SRV | Internal key |
| `employeeCode` | string | R | ADM | Human-facing `EMP-1042`. Format `^EMP-\d{3,5}$`. Unique per org |
| `name` | string | R | ADM | ≥ 3 chars |
| `designation` | string | R | ADM | Free text |
| `department` | string | **O** | ADM | **Free text, optional.** Empty string when unset — never null on the wire |
| `region` | string | **O** | ADM | **Free text, optional.** Same empty-string convention |
| `email` | string | R | ADM | Must contain `@`; unique, used as login |
| `phone` | string | R | ADM | ≥ 10 digits after stripping non-digits |
| `avatarUrl` | string (url) | O | ADM/EMP | Empty ⇒ UI falls back to initials |
| `bannerUrl` | string (url) | O | ADM | Empty ⇒ gradient fallback |
| `joinedOn` | date | R | ADM | Not in the future; picker floor 2015 |
| `reportingTo` | string | R | ADM | Currently rendered as `"<admin name> (<role>)"`. Store the manager's id and render the label server-side |
| `bloodGroup` | enum string | O | ADM | `A+ A- B+ B- O+ O- AB+ AB-`, or empty |
| `address` | string | O | ADM | Multi-line free text |
| `active` | bool | R | ADM | Lives on `TeamMember` today. Deactivated employees stay in the roster and are excluded from alerts |
| `initials` | string | D | — | First+last initial, `?` when the name is blank |
| `firstName` | string | D | — | First whitespace-separated token |

> **Optionality change.** `department` and `region` were single-select pickers over a
> hard-coded list. They are now free-text and optional: a hire is often created before the
> org placement exists, and zone names are not a closed set. Roster search still matches
> both fields. Every display site tolerates an empty value (`—` in label/value rows, the
> line is dropped in the profile header, the roster subtitle collapses to just the code).

### 1.2 Admin — `AdminUser`

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | SRV | |
| `adminCode` | string | R | SRV | `ADM-001` |
| `name` | string | R | ADM | |
| `role` | string | R | ADM | Job title: "Zonal Manager", "Operations Admin" |
| `email` | string | R | ADM | Login |
| `phone` | string | R | ADM | |
| `avatarUrl` | string | O | ADM | |
| `region` | string | O | ADM | Scope hint, not an access rule in this build |
| `initials` | string | D | — | |

### 1.3 Write payload — `EmployeeDraft`

Create when `id` is null, update otherwise. Fields: `id?`, `name`, `employeeCode`,
`designation`, `department` (**may be empty**), `email`, `phone`, `region` (**may be
empty**), `reportingTo`, `joinedOn`, `bloodGroup?`, `address?`.
Not settable from the form: `avatarUrl`, `bannerUrl`, `active`.

---

## 2. Workday chain

```
Employee → Attendance (one per calendar day)
             └─ WorkSession (1..n)
                  ├─ LocationLog (n)
                  └─ StopRecord (n) → CompanyVisit (0..1) → VisitReport (0..n)
```

### 2.1 `Attendance` — one workday rollup

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | SRV | |
| `date` | date | R | SRV | Local calendar day, no time part |
| `status` | enum | R | SRV | **Binary since 2026-11-08 (built/untracked/not deployed):** `present absent holiday weekend noData`. **`partial` is retired** — never emitted again; the enum value lingers only as a legacy name (migration 038 folded all historical `partial` → `present`). `02` §3.5 |
| `joiningTime` | datetime | O | SRV | First **valid** session start — the authoritative joining time. Null when absent |
| `endTime` | datetime | O | SRV | Last session close. Null while the day is open |
| `sessionCount` | int | R | SRV | |
| `workedDuration` | duration | R | SRV | **Evidence-bounded since 2026-11-06 (built, not deployed).** Sum of session durations, excludes gaps *between* sessions — and each session now runs `started_at → last evidence`, not `started_at → ended_at ?? now`. An **interior** dark gap counts; a **trailing** dark tail does not; a session with no fixes contributes 0. Same field, same type, **corrected value** (`02` §3.16, `09`) |
| `distanceKm` | double | R | SRV | Sum of session distances. **Still the verified figure**, never verified + incomplete |
| `verifiedDistanceKm` | double? | O | SRV | **Migration 036, built / untracked / not deployed.** Alias of `distanceKm` for a v2 row. **`null` means the row predates the engine** — render as "not measured", never 0 |
| `incompleteDistanceKm` | double? | O | SRV | Distance across INCOMPLETE gaps, deliberately kept **out** of the headline figure so an under-count becomes visible and attributable instead of silent. Same null rule |
| `gpsCoveragePercent` | double? | O | SRV | Duration-weighted across the day's sessions — **not** the mean of their percentages. Same null rule |
| `companiesVisited` | int | R | SRV | Distinct companies, not visit count |
| `reportsSubmitted` | int | R | SRV | |
| `stopDuration` | duration | R | SRV | Total time stationary |
| `longestStop` | duration | R | SRV | Feeds the long-stop alert history |

**`joiningTime` / `endTime` and `workedDuration` are now allowed to disagree, by design.**
The first pair is the **declaration** — `min(session start)` and `max(session end)`,
untouched by the evidence-bounding above. So a day can legitimately read "joined 09:00,
ended 18:00" with **3.5 h worked**. Both numbers are honest and they answer different
questions, but nothing on any screen says so yet — an open copy/UI item for `design-agent`.

**`status` can still change on a day that is already closed, but NOT because of worked time
any more.** As of 2026-11-08 (binary model, built/untracked/not deployed) `Engine::classifyDay`
**dropped the four-hour threshold**: `worked_seconds` no longer grades the day, so correcting
phantom hours can no longer flip the verdict — the old `present` → `partial` flip is **gone
with the status itself**. What *can* still re-derive a closed day's status is the **calendar**:
creating a holiday on an already-worked date flips `present` → `holiday` (and delete flips it
back), and editing `weekend_days` re-derives today + open sessions. That re-derivation is
read-time and scoped — closed historical days are not retroactively re-graded by a calendar
edit — but `POST /admin/holidays` *does* re-derive the stored rows of employee-days worked on
the affected date(s). One consequence still reaches the API: `GET /attendance` caches past
windows for 24 h on the premise that they cannot change — see `02` §3.16.

### 2.2 `WorkSession`

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | SRV | Client generates a temp id offline; server reconciles by `clientId` |
| `index` | int | R | SRV | 1-based within the day — rendered as "Session 2" |
| `startTime` | datetime | R | EMP | Stamped from the **first GPS fix**, not the button tap |
| `endTime` | datetime | O | EMP | Null ⇒ session open |
| `distanceKm` | double | R | SRV | Computed from the fix chain, **gated** since 2026-11-05 — weak fixes and sub-threshold hops do not count (`02` §3.12) |
| `locationPoints` | int | R | SRV | Count of accepted fixes — the **raw** stored count, not the number that moved `distanceKm` |
| `verifiedDistanceKm` | double? | O | SRV | **Migration 036, built / untracked / not deployed.** `null` = row predates the engine, which is **not** 0 |
| `incompleteDistanceKm` | double? | O | SRV | Distance the engine measured but cannot vouch for, across INCOMPLETE gaps. Same null rule |
| `gpsCoveragePercent` | double? | O | SRV | Same null rule |
| `distanceAlgoVersion` | int | R | SRV | `1` = the pre-036 rule, frozen at session creation and never upgraded. This is what makes historical distances immovable across the algorithm change |
| `startLatitude` | double | R | EMP | |
| `startLongitude` | double | R | EMP | |
| `isOpen` | bool | D | — | `endTime == null` |
| `durationAt(now)` | duration | D | — | Live timer |

### 2.3 `LocationLog` — one GPS fix (highest-volume record)

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | EMP | Device-generated; server keeps it for idempotent replay |
| `sessionId` | string | R | EMP | |
| `latitude` | double | R | SYS | |
| `longitude` | double | R | SYS | |
| `accuracy` | double (m) | R | SYS | **Corrected 2026-11-06: weak fixes are NOT dropped.** They are uploaded with a `lowAccuracy` flag (`location_service.dart:891`) and stored; accuracy is **never** a rejection reason at ingest (the reasons are `JUMP \| DUPLICATE \| MALFORMED`). `minAccuracyMetres` is applied at **read time**, twice — to classify a fix as rough for drawing, and to decide whether it may *measure* a leg of derived distance. This row said "dropped before upload" and had been wrong since 2026-10-31 |
| `speedKmh` | double | R | SYS | Drives `MovementStatus` against `movementSpeedThresholdKmh` |
| `recordedAt` | datetime | R | SYS | Device clock. Server also stamps `received_at` — clock skew is real |
| `syncState` | enum | R | EMP | `synced queued failed`. Client-side only; the server never returns anything but `synced` |

Volume: one fix per `locationIntervalSeconds` (default 30 s) per open session.
≈ 1 000 fixes / employee / 8 h day. This is the table that dictates the schema.

### 2.4 `SyncSnapshot` — offline queue state (client-only, never persisted server-side)

`queued` int R · `failed` int R · `lastSyncedAt` datetime O · `isOnline` bool R ·
`isClean` D (`queued == 0 && failed == 0`).

### 2.5 `StopRecord` — a dwell derived from the fix chain

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | SRV | |
| `sessionId` | string | R | SRV | |
| `arrival` | datetime | R | SRV | |
| `departure` | datetime | O | SRV | Null while the stop is open |
| `latitude` / `longitude` | double | R | SRV | Centroid of the clustered fixes |
| `radiusMetres` | double | R | SRV | Observed cluster radius, ≤ `stopRadiusMetres` |
| `visitId` | string | O | SRV | Null until promoted to a company visit |
| `isLinked` | bool | D | — | `visitId != null` |
| `durationAt(now)` | duration | D | — | |

Derivation: a run of fixes staying inside `stopRadiusMetres` for at least
`stopThresholdMinutes`. No geofence needed. Re-evaluated on read when config changes.

### 2.6 `CompanyVisit`

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | SRV | |
| `sessionId` | string | R | SRV | |
| `companyId` | string | R | SRV | The visit **is** a known-company event; walk-ins stay `StopRecord`s |
| `branchId` | string | O | SRV | |
| `stopId` | string | O | SRV | The stop it was promoted from |
| `arrival` / `departure` | datetime | R / O | SRV | |
| `latitude` / `longitude` | double | R | SRV | |
| `status` | enum | R | SRV | `inProgress completed unassigned` |
| `reportIds` | string[] | R | SRV | **A visit can carry many reports — never collapse to one** |
| `dealReference` | string | O | EMP | |
| `hasReport` | bool | D | — | |
| `durationAt(now)` | duration | D | — | |

---

## 3. Reports

### 3.1 `VisitReport`

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | SRV | |
| `companyName` | string | R | EMP | **Typed by the seller. Never looked up** |
| `branchName` | string | O | EMP | Free text |
| `companyId` | string | O | SRV | Set only when filed against a detected visit — this is what pins the report on the admin map |
| `branchId` | string | O | SRV | |
| `visitId` | string | O | SRV | Null when written outside a detected visit |
| `sessionId` | string | R | EMP | |
| `title` | string | R | EMP | |
| `body` | string | R | EMP | |
| `imageCount` | int | R | EMP | Always sent. Count only — the attachments themselves come from `?include=images` (§3.3) |
| `images` | `ReportImage[]` | O | SRV | **Key absent when not requested, `[]` when requested and empty** — the two must stay distinguishable (§3.3) |
| `submittedAt` | datetime | R | SRV | |
| `latitude` / `longitude` | double | R | EMP | Where the report was written |
| `status` | enum | R | EMP | **Upload** vocabulary: `draft queued uploading submitted failed reviewed` |
| `dealValue` | string | O | EMP | Free text as typed — currency/format not enforced |
| `followUpOn` | date | O | EMP | |
| `sales` | `ProductSaleLine[]` | R | EMP | Empty when nothing was sold |
| `paymentReceived` | string | O | EMP | Amount actually collected, as typed |
| `unitsSold` | int | D | — | Σ `sales[].units` |
| `hasSale` | bool | D | — | |
| `preview` | string | D | — | Body flattened, 120 chars |

`ReportStatus.isPending` (D) = `draft \|\| queued \|\| uploading`.

### 3.2 `ProductSaleLine`

`productId` R · `productName` R (**denormalised** so the report still reads correctly
after a catalogue edit) · `category` R · `colorName` R · `colorArgb` int R · `units` int R ·
`imageUrl` O (shipped 2026-11-05).

`imageUrl` is the **one field on this line that is not frozen at sale time**: it is resolved
at read time from the live catalogue for that product + colour, and is `null` when the
catalogue has no photo (the client falls back to painted artwork). So re-shooting a colour's
photo changes the photo on older reports. Recorded as a deliberate exception to the
"what the seller typed is what gets stored" rule — a product photograph is not history, and
storing the URL would pin a report to a file that may later be replaced. `02` §3.8 is
authoritative.

### 3.3 Attachments — **modelled and shipped 2026-11-05**

`ReportImage`: `id`, `reportId`, `url`, `thumbnailUrl`, `width`, `height`, `bytes`,
`capturedAt`, `latitude?`, `longitude?`. Served by `?include=images` on `GET /reports` and
`GET /reports/{id}`, ordered by `position`; `02` §3.8 is authoritative. Upload stays a
separate multipart step (`POST /reports/{id}/images`) so a report can submit while its images
are still queued — the client's `uploadState` is device-local and not a wire field.

`url` / `thumbnailUrl` are document-root relative and resolve against the **API origin**, not
`baseUrl`. `thumbnailUrl` is plumbed end to end but nothing generates thumbnails yet, so it is
`null` on every row today.

The files themselves are served as **unauthenticated static assets** (unguessable UUIDv4
capability URLs) — `02` §3.8 states the consequence and the open decision, which matters here
because this payload also carries each photo's `latitude`/`longitude`.

### 3.4 `ReportDraft` (write payload)

`companyName` R, `branchName?`, `companyId?`, `branchId?`, `title` R, `body` R,
`imageCount` R, `visitId?`, `dealValue?`, `followUpOn?`, `sales[]`, `paymentReceived?`.
Needs one addition for the live app: **`clientId`** (device-generated uuid) so an offline
retry cannot create a duplicate report.

### 3.5 `ReportReview` — admin verdict

`reportId` R · `decision` enum R (`pending approved rejected`) · `note` O · `reviewedBy` O
· `reviewedAt` O. Derived: `isDecided`.

> Deliberately **separate from `ReportStatus`**. Review is admin metadata; upload state is
> the employee's. Conflating them would make the employee-side status lie.

### 3.6 `ReportInboxItem` — admin envelope

`report` R · `employee` R · `review` R · `companyName` R · `branchName` O · `decision` D.

---

## 4. Customers

`Company`: `id` R, `name` R, `branches[]` R, `category` R (`Distributor`/`Retail`/`Corporate`).
Derived: `branchById(id)`.

`Branch`: `id` R, `name` R, `address` R, `latitude` R, `longitude` R.

Companies are a reference list used to *detect* visits. They are **not** a picker on the
report form and there is no per-employee territory assignment — sellers work a loose,
overlapping territory that nobody assigns.

---

## 5. Catalogue

### 5.1 `Product`

| Field | Type | Req | Owner | Notes |
| --- | --- | --- | --- | --- |
| `id` | string | R | SRV | |
| `category` | enum | R | ADM | `scooty bike bicycle others` (wire value = enum name) |
| `brand` | string | R | ADM | |
| `name` | string | R | ADM | |
| `modelCode` | string | R | ADM | Searchable |
| `rating` | double 0–5 | R | ADM | |
| `warrantyYears` | int | R | ADM | |
| `warrantyNote` | string | R | ADM | e.g. "3 yrs vehicle + 4 yrs battery" |
| `rangeKm` | int | R | ADM | Mileage per charge |
| `topSpeedKmph` | int | R | ADM | |
| `chargingTime` | string | R | ADM | Free text ("4–5 hrs") |
| `batteryCapacity` | string | R | ADM | |
| `motorPower` | string | R | ADM | |
| `loadCapacityKg` | int | R | ADM | |
| `colors` | `ProductColor[]` | R | ADM | ≥ 1 |
| `highlights` | string[] | O | ADM | |
| `listedAt` | datetime | O | SRV | Null for the seed catalogue — nobody listed those, so they are never "new" |
| `active` | bool | R | ADM | **Admin-only.** Delisting hides it from the seller picker without deleting reports that reference it |
| `isNew` | bool | D | — | `listedAt` within 7 days |
| `displayName` | string | D | — | `"$brand $name"` |
| `specSheet` | (label,value)[] | D | — | Warranty, range, top speed, charging, battery, motor, load, model code |

**There is deliberately no price field**, on `Product` or `ProductDraft`. A test asserts
the spec sheet never leaks one.

### 5.2 `ProductColor`

`name` R · `argb` int R (`0xAARRGGBB`, so the data layer stays Flutter-free) ·
`imageUrls` string[] O (**gallery is per colour** — a separate image set per colourway) ·
`inStock` bool R · `imageCount` D.

### 5.3 `ProductDraft`

`Product` minus derived members, minus `listedAt`, minus `active`; `id` null ⇒ create.

---

## 6. Timeline and feed

### 6.1 `ActivityEvent` — flattened chronological day

`id` R · `type` enum R · `time` R · `endTime` O · `title` R · `subtitle` O ·
`companyName` O · `branchName` O · `duration` O · `reportId` O · `visitId` O ·
`isAlert` bool R (renders the row in the warning colour).

`ActivityType`: `dayStarted sessionStarted travelling arrived stayed reportSubmitted left
sessionEnded dayEnded trackingIssue`.

Built server-side from sessions + stops + visits + reports so **the client never joins them**.

### 6.2 `AppNotification`

`id` R · `kind` enum R (`newProduct productUpdated reportReviewed announcement`) ·
`title` R · `message` R · `createdAt` R · `read` bool R · `productId` O · `reportId` O.
Derived: `unreadCount`, `hasUnread` (feed-level).

Producers today: admin lists/updates a product. The kinds are open so a review verdict or
a broadcast lands without a model change.

---

## 7. Aggregates

### 7.1 `DaySummary` (Home "Today's Summary")

`joiningTime` O · `endTime` O · `workedDuration` R · `sessionCount` R · `distanceKm` R ·
`companiesVisited` R · `reportsSubmitted` R · `stopDuration` R · `longestStop` R.
Same nine values as `Attendance` minus id/date/status — see §12.

### 7.2 `PeriodStatistics` (employee calendar stats tab)

`rangeLabel` R · `averageJoiningTime` R (time-of-day carried as a datetime, date part
ignored) · `averageWorkedDuration` R · `averageDistanceKm` R · `totalDistanceKm` R ·
`averageVisitsPerDay` R · `totalVisits` R · `totalReports` R · `averageReportsPerDay` R ·
`workingDays` R · `presentDays` R · `absentDays` R · `attendancePercent` R ·
`dailyDistance` `DailyMetric[]` R (oldest first).

### 7.3 `TeamStatistics` (admin analytics) — same shape, team scope

`rangeLabel` · `perEmployee` `EmployeeMetric[]` · `totalDistanceKm` · `totalVisits` ·
`totalReports` · `averageDistanceKm` (per employee per working day) ·
`averageWorkedDuration` · `attendancePercent` · `workingDays` · `dailyDistance` ·
`headcount` D.

### 7.4 `EmployeeMetric`

`employeeId` · `name` · `initials` · `distanceKm` · `visits` · `reports` · `presentDays` ·
`absentDays` · `attendancePercent` · `averageWorkedDuration`. All R.

### 7.5 `DailyMetric`

`date` R · `value` double R (distance km) · `secondaryValue` int R (visit count).

### 7.6 `StatsRange`

`thisWeek thisMonth lastMonth custom`. `custom` requires `from` + `to`.

---

## 8. Screen payloads (composed, one request per render)

### 8.1 `HomeSnapshot` — employee Home

`status` `WorkStatus` R · `movement` R · `locationHealth` R · `sessions[]` R ·
`summary` R · `activity[]` R · `visits[]` R · `stops[]` R · `lastFix` O · `sync` R.
Derived: `activeSession` (first open session).

### 8.2 `EmployeeDay` — admin mirror of the above

`employee` R · `date` R · `attendance` O · `status` R · `movement` R · `sessions[]` R ·
`stops[]` R · `visits[]` R · `reports[]` R · `activity[]` R · `summary` R · `lastFix` O.
Derived: `isEmpty`.

### 8.3 `TeamMember` — one live roster row

`employee` R · `status` R · `movement` R · `locationHealth` R · `summary` R ·
`attendanceStatus` R · `active` bool R · `lastFix` O · `activeSince` O (start of the open
session) · `openStopDuration` O · `openStopCompanyId` O.
Derived: `isLongStop(threshold)`, `isDegraded`, `isWorking`.

### 8.4 `TeamOverview` — admin dashboard

`date` R · `members[]` R · `totalDistanceKm` · `totalVisits` · `totalReports` ·
`presentCount` · `absentCount` · `workingCount` · `idleCount` · `offlineCount` ·
`pendingReviewCount` · `longStopCount` — all R. Derived: `headcount`, `attendancePercent`.

### 8.5 `RouteTrack` — map payload

`employeeId` R · `date` R · `points` `LocationLog[]` R · `sessions[]` R · `stops[]` R ·
`visits[]` R · `totalDistanceKm` R.
Derived: `bounds` (`LatLngBounds`), `segments` (points grouped by session so a break never
draws a phantom straight line), `firstFixAt`, `lastFixAt`, `queuedCount`, `isEmpty`
(< 2 points).

`LatLngBounds`: `minLat maxLat minLng maxLng` + `spanLat spanLng midLat midLng padded()` — all derived geometry, client-side.

### 8.6 `TeamAttendanceGrid` / `TeamAttendanceRow`

Grid: `month` R · `days` `date[]` R · `rows[]` R.
Row: `employee` R · `byDayKey` `Map<int, AttendanceStatus>` R (key `yyyymmdd`, so the cell
lookup needs no date arithmetic) · `presentDays` · `absentDays` · `partialDays` **(deprecated,
always `0` since the 2026-11-08 binary model — key kept so the parser does not break, slated
for removal)** · `attendancePercent`.

---

## 9. Status vocabularies

| Enum | Values | Where it comes from |
| --- | --- | --- |
| `WorkStatus` | `notStarted working idle locationUnavailable offline ended` | Server, from session state + fix recency |
| `MovementStatus` | `moving stationary unknown` | Last fixes vs `movementSpeedThresholdKmh` |
| `LocationHealth` | `ok serviceDisabled permissionDenied permissionDeniedForever backgroundDenied poorAccuracy noInternet` | **Device only** — reported upward, never authored by the server. **`poorAccuracy` is a legacy wire value since 2026-11-05:** weak GPS is no longer a status anybody is shown. The device no longer *produces* it (`LocationService.health()` stopped consulting accuracy; the 3-interval `_accuracyIsPoor` window is deleted), the client folds it onto the same arm as `ok` for title/message/action, and the server accepts it and does nothing with it. It stays on the wire only because builds already in the field (0.1.7) send it |
| `AttendanceStatus` | `present absent holiday weekend noData` (**binary since 2026-11-08** — `partial` retired, never emitted; see §2.1) | Server calendar rules — holiday > weekend > (no session: future `noData`, past `absent`) > `present` |
| `SyncState` | `synced queued failed` | Device |
| `VisitStatus` | `inProgress completed unassigned` | Server |
| `ReportStatus` | `draft queued uploading submitted failed reviewed` | Device (upload), server flips to `reviewed` |
| `ReviewDecision` | `pending approved rejected` | Admin |
| `ProductCategory` | `scooty bike bicycle others` | Admin |
| `NotificationKind` | `newProduct productUpdated reportReviewed announcement` | Server |
| `ActivityType` | 10 values, §6.1 | Server |
| `StatsRange` | `thisWeek thisMonth lastMonth custom` | Client request param |

Derived predicates the client relies on: `WorkStatus.isSessionOpen`, `WorkStatus.isDegraded`,
`LocationHealth.blocksStart`, `ReportStatus.isPending`, `ReviewDecision.isDecided`.
Keep these client-side — they are presentation rules, not stored state.

---

## 10. Admin-owned tracking configuration

`TrackingConfig` — **one row per organisation**, written only by the admin console
(Admin → Tracking rules), read by every employee device.

| Field | Type | Default | Editable in UI | Effect |
| --- | --- | --- | --- | --- |
| `locationIntervalSeconds` | int | 30 | ✅ 15–120 | Fix capture cadence while a session is open |
| `minAccuracyMetres` | double | 50 | ✅ 20–150 | **Corrected 2026-11-06: nothing is dropped and everything is plotted.** Read-time threshold with two uses — the rough-fix classification the admin route screen draws, and the gate deciding which fixes may *measure* derived distance. Retuning it reclassifies history with no rewrite, which is the property that makes storing a boolean wrong (`02` §3.11). Since migration 036 it is also the **moderate/poor band edge**; the server default moves to **30** for freshly materialised orgs |
| `stopRadiusMetres` | double | 75 | ✅ 25–200 | Must stay inside this radius to be a stop candidate |
| `stopThresholdMinutes` | int | 10 | ✅ 5–30 | Minimum dwell before a stop is recorded |
| `longStopThresholdMinutes` | int | 45 | ✅ 20–120 | Dwell after which the dashboard raises a red alert |
| `movementSpeedThresholdKmh` | double | 2 | ❌ read-only | Below this ⇒ stationary |
| `maxJumpKmh` | double | 180 | ❌ read-only | Implied speed above this marks an invalid GPS jump |
| `offlineThresholdMinutes` | int | 10 | ✅ 5–60 | No upload this long ⇒ status Offline |
| `locationUnavailableThresholdMinutes` | int | 15 | ❌ read-only | No valid fix this long ⇒ Location Unavailable |
| `syncBatchSize` | int | 50 | ❌ read-only | Queued fixes flushed per request |

**Semantics that must survive to the backend**

1. Config is **organisation-scoped**, not per-employee and not per-device. A device that
   has never fetched it falls back to `TrackingConfig.defaults`.
2. Changing a threshold **does not rewrite history**. Stops, long-stop alerts and movement
   state are classified at read time against the config in force.
3. The device must re-read config at login and at each session start, so a cadence change
   takes effect without an app update. Recommended: return a `version`/`updatedAt` and let
   the device short-circuit on an unchanged value.
4. The read-only fields are read-only *in the UI*, not in the data model — the API must
   still accept them so the values can be tuned without a client release.

## 11. Device preferences — `SettingsController`

Per user + device, **not** tracking config. In memory today; needs persistence.

`themeMode` (`system light dark`) · `language` (`English`, …) · `notificationsEnabled` ·
`reportReminders` · `sessionReminders` · `systemNotifications` · `highAccuracyMode` ·
`syncOnMobileData` · `batterySaver`. All R, bool unless noted.

**`highAccuracyMode`, `syncOnMobileData` and `batterySaver` are RETIRED as of 2026-11-05
(built, not deployed).** They are now **server-fixed and read-only**: `GET /me/preferences`
reports `true, true, false` as literals rather than reading the stored columns, and a
`PATCH` carrying them is **ignored, not rejected** (`02` §3.2). The response shape is
unchanged — all nine fields are still on the wire — so an older build keeps working and
simply reads the behaviour it should now have. The columns stay in the database; migration
036 resets them to those values on every row.

This paragraph previously said admin config sets a ceiling and the device preference may
only make collection *less* aggressive. **That was never true in the code** — verified
before the retirement: nothing in `location_service.dart` read any of the three, so they
were inert, and they were **not** a cause of the under-counted distance that started the
universal-distance work. Removing them changed no capture behaviour. The reason they went is
the rule, not a bug: tracking config is org-wide and admin-only, and an employee who can
soften their own capture settings can soften their own odometer.

The client still holds all three in `settings_controller.dart`; removing them there, and the
three switches in the settings UI, is an open `mobile-agent` item.

---

## 12. Duplication worth collapsing

| Pair | Overlap | Action |
| --- | --- | --- |
| `Attendance` ↔ `DaySummary` | 9 of 12 fields identical | One `DaySummary` object embedded in `Attendance` |
| `PeriodStatistics` ↔ `TeamStatistics` | Same aggregate, different scope | One statistics resource with a `scope` param |
| `HomeSnapshot` ↔ `EmployeeDay` | Same day payload, admin adds `employee`/`attendance`/`reports` | One day resource, `me` vs `{employeeId}` |
| `EmployeeRepository.products()` ↔ `AdminRepository.products()` | Same list; admin also sees delisted | One catalogue endpoint, `includeDelisted` gated by role |
| `route()` ↔ `teamRoutes()` | Single track is the n = 1 case | One routes endpoint taking an id list |
| `reports()` ↔ `employeeReports()` ↔ `inbox()` | Same report list, different filters and joins | One reports endpoint with filters + `include` |
| `TeamMember.summary` ↔ `Attendance` | Same nine numbers again | Reuse the embedded `DaySummary` |

These drive the endpoint consolidation in `02-API-PLAN.md`.

---

## 13. Data the UI needs that no model carries yet

| Gap | Needed for |
| --- | --- |
| **Auth**: token, refresh token, expiry, role, deviceId, push token | Both shells. Both logins currently accept anything |
| ~~**Report attachments** (§3.3)~~ | **Closed 2026-11-05** — `ReportImage` + `?include=images` shipped and deployed |
| **`clientId` on every offline-created record** (session, fix, report) | Idempotent replay — without it a retry duplicates |
| **`received_at`** alongside `recordedAt` on a fix | Device clock skew, late-arriving offline batches |
| **Audit trail**: who changed a config/employee/product, and when | Tracking rules and roster edits are unattributed |
| **Manager id** behind `Employee.reportingTo` | It is a rendered string today |
| ~~**Holiday calendar**~~ | **Closed 2026-11-08 (built/untracked/not deployed)** — `holidays` table (now with `one_time`/`yearly` recurrence) + admin CRUD `GET/POST/DELETE /admin/holidays`; `Engine::holidayOn` is the single read-time source. `02` §3.5a |
| ~~**Weekend definition** per org~~ | **Closed 2026-11-08 (built/untracked/not deployed)** — `organizations.weekend_days` now has an admin write path `GET/PUT /admin/weekend-days`; was seed-time only |
| **Pagination cursors** on reports / fixes / notifications | Every list is unbounded today |
| **`Product.active`** as a first-class field | Currently a side-channel `delistedProductIds()` set |
| **Push/notification delivery receipts** | `AppNotification.read` is local-only |

---

## 14. Coverage checklist

Every model in `lib/data/models/` accounted for:

| Model | Section |
| --- | --- |
| `Employee` | §1.1 |
| `AdminUser` | §1.2 |
| `EmployeeDraft` | §1.3 |
| `Attendance`, `AttendanceStatus` | §2.1, §9 |
| `WorkSession` | §2.2 |
| `LocationLog`, `SyncState` | §2.3, §9 |
| `SyncSnapshot` | §2.4 |
| `StopRecord` | §2.5 |
| `CompanyVisit`, `VisitStatus` | §2.6, §9 |
| `VisitReport`, `ReportStatus` | §3.1, §9 |
| `ProductSaleLine` | §3.2 |
| `ReportDraft` | §3.4 |
| `ReportReview`, `ReviewDecision` | §3.5, §9 |
| `ReportInboxItem` | §3.6 |
| `Company`, `Branch` | §4 |
| `Product`, `ProductCategory` | §5.1, §9 |
| `ProductColor` | §5.2 |
| `ProductDraft` | §5.3 |
| `ActivityEvent`, `ActivityType` | §6.1, §9 |
| `AppNotification`, `NotificationKind` | §6.2, §9 |
| `DaySummary` | §7.1 |
| `PeriodStatistics` | §7.2 |
| `TeamStatistics` | §7.3 |
| `EmployeeMetric` | §7.4 |
| `DailyMetric` | §7.5 |
| `StatsRange` | §7.6 |
| `HomeSnapshot` | §8.1 |
| `EmployeeDay` | §8.2 |
| `TeamMember` | §8.3 |
| `TeamOverview` | §8.4 |
| `RouteTrack`, `LatLngBounds` | §8.5 |
| `TeamAttendanceGrid`, `TeamAttendanceRow` | §8.6 |
| `WorkStatus`, `MovementStatus`, `LocationHealth` | §9 |
| `TrackingConfig` | §10 |
| `SettingsController` | §11 |
