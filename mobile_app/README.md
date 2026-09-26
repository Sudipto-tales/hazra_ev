# Field Tracker — Employee + Admin Mobile App (design pass)

Flutter UI for the salesman / employee tracking system. **Static data only** —
no backend, no real GPS plugin, no network calls. Every screen is driven by an
in-memory mock so the layouts, states and flows can be reviewed end to end.

The app opens on a **role-select screen** with two cards: *Employee Login* and
*Restricted · Authorized Personnel Only*. Each opens its own login form and its
own shell — the employee app and the admin console.

## Run

```bash
# platform folders are not committed — generate them once
flutter create --platforms=android,ios .

flutter pub get
flutter run
```

Requires Flutter **3.27 or newer** (uses `Color.withValues`). Zero third-party
packages, so `pub get` never fails on a version conflict.

## What is implemented

### Employee app

| Tab | Screen | Notes |
| --- | --- | --- |
| Home | Status hero, Start/End Day, today's summary, visits, activity timeline | Live session timer, degraded-tracking banners |
| Reports | Today's reports, collapsible previous-day groups, detail, composer | Free-text company, EV sale card, multiple reports per shop |
| Calendar | Month attendance grid, day detail, statistics tab | Range filters + distance chart |
| Profile | Banner header, settings, tracking sheet, privacy/help/about | Theme + notification preferences |

Flows worth clicking:

- **Start Day** — checks location health first. Profile → Settings → *Simulate
  location state* switches the mock service into "location off" / "permission
  denied" so the blocking dialog and the refusal to open a session are visible.
  That sheet also offers to reset the day back to *Not started*.
- **Break / Resume** — closes the open session and opens a new one, which is how
  a day ends up with several sessions.
- **Tracking sheet** — tap the last-update line on the hero card: permission,
  accuracy, speed, offline queue depth, last sync.
- **New report** — four collapsible sections: *where you visited*, *report*,
  *products & sale*, *deal & follow-up*. Photo tiles, upload progress, success
  state. See **The report composer** below.

### Admin console (`ADM-001`)

| Tab | Screen | Notes |
| --- | --- | --- |
| Dashboard | Live team status, long-stop / GPS-lost / not-started alerts, KPI grid, attendance split | Alerts read the editable long-stop threshold |
| Team | Roster with search + status filter, employee detail, **route map** | Detail reuses the employee timeline and visit tiles |
| Reports | Review inbox with filters, full report, Approve / Send back with a note | Review state is separate from upload state |
| Insights | Team attendance matrix (pinned name column), team analytics + ranking | Same attendance colours as the employee calendar |
| Admin | Admin profile, employee add/edit, **product catalogue**, territory assignment, tracking rules, settings | Management screens are pushed routes, not tabs |

Flows worth clicking:

- **Route map** — Team → an employee → *View route on map*. Polyline per
  session, direction chevrons, geofence circles, numbered visit pins, S/E caps,
  a real scale bar, pinch-zoom and tap-a-pin-for-detail. Scrub back a day to see
  a different route; scrub to a weekend to see the empty state.
- **Review a report** — Reports → a pending report → *Send back* with a note,
  then reopen it; the note and the verdict are shown against the report.
- **Territory** — Admin → an employee → *Assign companies*. The itinerary is
  generated from the assignment, so saving visibly changes that employee's
  visits and route.
- **Products** — Admin → *Products* → *List product*. Fill the specs, add a
  couple of colours, save; the field team gets a notification and the model is
  immediately selectable in the report composer's picker. Flip a product's
  *Listed* switch off and it greys out here but disappears from the seller's
  picker — delisting is not deletion, so reports that already reference it still
  read correctly.
- **Tracking rules** — Admin → *Tracking rules* → drop the long-stop threshold
  and go back to the dashboard; the alert count reacts.

## The report composer

Two things about it are deliberate.

**Company and branch are text inputs, not pickers.** A seller's route is not
fixed, so the shop they walk into is often on no master list. `VisitReport`
therefore carries `companyName` / `branchName` as free text and keeps
`companyId` / `branchId` as an *optional* link, set only when the report was
filed against a detected visit — that link is what still puts the report on a
pin on the admin route map. Nothing displays a company by looking an id up any
more: the report card, the report detail, the reports filter and the admin
review inbox all read the seller's own words. The company filter chips are built
from the names that actually appear in the data, because with an open field
there is no list to enumerate.

**The EV sale card.** Pick a category (*Scooty / Bike / Bycycle / Others*), the
model list for that category is fetched (`products(category:)` →
`GET /api/catalog/products`), and models are chosen with a checkbox
multi-selector. Each selected model gets a card: warranty plate, rating, the
image, an **Available Colors** row, the headline specs and a units-sold stepper.
Tapping the image opens the full gallery for the selected colour plus the spec
sheet — warranty, mileage/range, top speed, charging, battery, motor, load.
Selections survive a category switch, so one report can mix a scooter and a
bicycle. Below the cards: total units and **payment received**.

**No price anywhere.** The catalogue has no price field and the sheet has no
price row; a test asserts the spec sheet never leaks one. Field sellers log units
and the amount they actually collected.

Product images are **per colour** (`ProductColor.imageUrls`) because that is how
the data arrives — whoever uploads a product uploads a set per colour. This build
bundles no assets and makes no network calls, so `ProductArtwork` paints the
vehicle in the selected colour from three angles instead. The behaviour is the
real one: change the colour, the pictures change. Swap the body of that widget
for `Image.network(color.imageUrls[variant])` when the catalogue is served.

**The route map uses no map package.** The app ships zero third-party
dependencies and has to work offline, so the route is drawn with a
`CustomPainter` over a grid plate rather than on OpenStreetMap tiles. The
projection is equirectangular with a cosine correction on longitude and a
*uniform* scale on both axes, so the shape of the route is never squashed.

## Free text where a picker would lie

The composer's company field is one instance of a rule the app applies twice. The other is
the **add-employee form**: department and region are free text and optional. A hire is
often created before the org placement is decided, and the zone names are not a closed
list, so a picker over a hard-coded list would block the common case and invent structure
that does not exist. Roster search still matches both fields — it filters on whatever
string is stored — and every display site tolerates an empty value: `—` in a label/value
row, the line dropped in the profile header, the roster subtitle collapsing to just the
employee code.

## Backend documentation

`docs/` carries the field-level data inventory, the endpoint plan and the database schema
— written from this codebase, each ending in a coverage table:

- [`docs/01-DATA-REQUIREMENTS.md`](docs/01-DATA-REQUIREMENTS.md)
- [`docs/02-API-PLAN.md`](docs/02-API-PLAN.md)
- [`docs/03-DATABASE-SCHEMA.md`](docs/03-DATABASE-SCHEMA.md)

## Architecture

```
lib/
  core/            theme tokens, spacing, formatters, tracking thresholds
  data/
    models/        Employee, Attendance, WorkSession, LocationLog,
                   StopRecord, CompanyVisit, VisitReport, Product, statistics
    mock/          the fake dataset: employee, admin, EV catalogue
                   (ProductStore is the live catalogue both sides share)
    repositories/  EmployeeRepository (abstract) + mock implementation
  services/        LocationService (abstract) + mock implementation
  state/           TrackingController, SettingsController, AppScope (DI)
  widgets/         design-system primitives shared by every feature
  features/        auth, shell, home, reports, calendar, profile
    admin/         shell, dashboard, employees, map, reports, insights,
                   manage, products, profile
```

Two seams matter:

1. **`EmployeeRepository`** — the UI never touches data directly. Method names
   mirror the planned endpoints (`home()` → `GET /api/employee/home`,
   `attendance()` → `/calendar`, `statistics()` → `/statistics`, …). Swap
   `MockEmployeeRepository` for an HTTP one in `main.dart`; no widget changes.
2. **`LocationService`** — all GPS/permission/queue behaviour sits behind this
   interface. The real implementation wraps the existing geolocation plugin and
   background service. `TrackingController` is the only thing that talks to it,
   so the "no session without a valid fix" rule lives in exactly one place.

Data model follows the intended chain:

```
Employee → Attendance (workday) → WorkSession → LocationLog
                                      ↓
                              StopRecord → CompanyVisit → VisitReport
```

## How the admin data layer works

The employee models carry **no owner id** — `/api/employee/*` takes its identity
from the auth token, so `Attendance`, `WorkSession`, `VisitReport`,
`CompanyVisit`, `StopRecord` and `LocationLog` are all ownerless by design.
Rather than retrofit an `employeeId` onto every one of them (and every
construction site in the employee write path), the admin side adds a parallel
layer:

```
AdminRepository (abstract)  →  MockAdminRepository  →  AdminMockData
        │                            │                      │
   /api/admin/*              in-memory write overlay   Map<employeeId, …>
```

Ownership lives in the repository and in admin-only envelope models
(`TeamMember`, `EmployeeDay`, `RouteTrack`, `ReportInboxItem`), which is honest:
admin endpoints really do carry `{id}` in the path.

Two rules keep the generated data coherent:

1. **Everything derives from the `Attendance` row.** Sessions, stops, visits,
   reports, the timeline and the route are all generated from that day's
   attendance record, so the map's distance cannot disagree with the calendar's.
   The route synthesiser bows each leg out and bisects the amplitude until the
   arc length matches the recorded kilometres.
2. **Employee 0 *is* `MockData.employee`.** For Rahul Ahmed today, the admin
   console delegates straight to the employee app's own fixtures, so both sides
   show the same day.

Seeds are derived by folding the id's code units, not `String.hashCode` — the
latter is not stable across VM releases, and an employee created at runtime
still has to get a plausible 120-day history.

## Deliberately not in this build

- **Backend / API** — nothing is wired to a server. Both repository interfaces
  are the seam.
- **Real geolocation** — the repository was empty when this was built, so there
  was no existing implementation to reuse. `LocationService` is the seam it
  plugs into when it exists.
- **Real basemap** — the admin route map is painted, not tiled. Swapping in
  Leaflet/OSM (the spec's Part B) is a `RouteMapView` replacement; the
  projection, markers and legend are already separated from the data.
- **Image capture** — the picker returns placeholder tiles rather than files.
- **Product photography** — no assets are bundled and no images are fetched, so
  the catalogue is drawn by `ProductArtwork`. The per-colour `imageUrls` field is
  already in the model.
- **Persistence** — settings, submitted reports, report reviews, employee edits,
  territory assignments and tracking rules all live in memory for the session
  only. Restarting the app resets them.
- **Real auth** — both login screens accept anything. The role you are in is
  expressed by which shell is on the navigator, not by a token.

## Conventions

- Colour, spacing and radius come from `core/theme`. No raw hex in features.
- Every list routes through the shared `EmptyState` / `ErrorState` /
  `LoadingCards` / `AlertBanner` widgets — no blank screens.
- Business thresholds (stop radius, dwell time, accuracy floor, offline
  window) are in `core/config/tracking_config.dart`, ready to be served by the
  backend later.
- Light and dark themes are both supported; test changes in both.
