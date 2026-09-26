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
  "config":       { "...": "TrackingConfig", "version": 7 },
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

This replaces `home()`, `activity()` and `employeeDay()`.

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
GET /routes?date=&subject=me|{id}|team&employeeIds=a,b,c&include=stops,visits,sessions&simplify=
```

Always returns `RouteTrack[]`. `subject={id}` returns one element; `subject=team` returns
one per active employee, **empty tracks included** so the map legend can still list who
did not work. Replaces `route()` + `teamRoutes()`.

`simplify=` (metres, Douglas–Peucker) is the one real payload lever here — a 1 000-point
day compresses to ~150 points with no visible change to the polyline. Send
`simplify=8` for the overview and `simplify=0` when the user zooms in.
`points` are delivered as a flat `[lat, lng, t, acc, spd, sessionIdx]` tuple array, not
objects — that is roughly a 4× size reduction on the largest payload in the system.

### 3.8 Reports — **one route serves three lists**

```
GET  /reports?subject=me|{id}|all&date=&from=&to=&status=&decision=&company=&query=
             &include=employee,review,sales&limit=&cursor=
GET  /reports/{id}?include=employee,review,sales
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
```

- **`POST /tracking/locations` is the only batched endpoint.** Body carries up to
  `config.syncBatchSize` fixes (default 50) as the same flat tuple array §3.7 uses.
  Response: `{"accepted": ["id"...], "rejected": [{"id":"…","reason":"ACCURACY"|"JUMP"|"DUPLICATE"}]}`
  so the device can drop them from its queue with certainty.
- The device filters on `minAccuracyMetres` **before** upload; the server re-checks and
  also applies `maxJumpKmh`. Client-side filtering is an optimisation, never the guarantee.
- Sessions carry a `clientId` because they are opened offline. A repeated open with the
  same `clientId` returns the existing session — that is what makes "Start Day" safe to
  retry on a dead network.
- Stops and visits are **derived server-side** from the fix chain. The client never posts
  them; posting them would let two devices disagree about what a stop is.
- `POST /tracking/health` is what turns a `TeamMember` red on the admin dashboard while the
  employee's GPS is off. Cheap, fire-and-forget, no response body needed.

### 3.12 Configuration

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

### 3.13 Notifications

```
GET   /notifications?unreadOnly=&updatedSince=&limit=&cursor=
PATCH /notifications/{id}            {"read": true}
POST  /notifications/read-all
```

Badge count comes from `/bootstrap` at launch and from `limit=0&unreadOnly=true`
afterwards; push delivers the increments.

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
