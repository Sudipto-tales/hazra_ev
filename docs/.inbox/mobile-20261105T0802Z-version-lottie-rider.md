---
agent: mobile-agent
date: 2026-11-05T08:02Z
status: done
needs-api: false
---

# Version easter-egg screen: the painted rider is now a Lottie

Folder touched: `mobile_app/` only (plus this entry).
Verification: `flutter analyze` **14 issues, 0 errors** — identical to the
baseline, none of them in a file this task touched · `flutter test -j 1`
**220 passed / 23 skipped / 0 failed** (219 before; +1 new).

## What changed

- `mobile_app/assets/anim/delivery_boy.json` — new. 205 KB of pure vector
  Lottie (446×446, 30 fps, frames 0–31 → a 1.03 s loop; five shape layers,
  no masks, no mattes, no embedded images). ~40 KB once the APK deflates it.
- `mobile_app/pubspec.yaml` — added `lottie: ^3.3.1` and the app's **first**
  `flutter: assets:` block. There was no `assets/` directory before this.
- `mobile_app/lib/features/profile/version_page.dart` — the rider is the
  Lottie; `_RidePainter` (the bike, the smoke and the road, ~145 lines) is
  deleted along with the now-unused `dart:math` import.
- `mobile_app/test/settings_about_test.dart` — one new case, "ships the rider
  animation it asks for, and credits it".

## The dependency

`^3.3.1`, resolving to **3.8.2** on this toolchain (Flutter 3.47.6 / Dart
3.13.5). The floor is not arbitrary: 3.3.1 is the last lottie release whose
own constraint still admits the Flutter 3.27 / Dart 3.6 floor this package
declares, so the declared floor stays true while the lockfile takes the
newest release the present toolchain supports. 3.3.2+ quietly requires Dart
3.9, and 3.8.x requires Flutter 3.47.

No native side, no platform registration, no Android/iOS change.

## What the screen does now

The three-stage choreography is **unchanged** and is still the point of the
screen: `_c` runs 2.6 s across `_ride` (0–0.68), `_smokeFade` (0.62–0.86) and
`_reveal` (0.84–1.0); rider crosses, exhaust clears, build identity fades up
in its place. Replay button unchanged.

What changed is only *what is being carried across the card*. There are now
two controllers and the split matters:

- `_wheels` repeats the file's own 1.03 s cycle. The animation rides in place
  — it does not translate — so the wheels spin, the exhaust puffs and the
  rider bobs while standing still in its own coordinate space.
- `_c` does the travelling, sliding that looping widget left→right with a
  `Positioned` driven by `_ride`.

Both are disposed. Three details worth keeping in mind if this is edited
again:

- `_wheels` is **stopped** when `_c` completes. The rider has left the frame
  by then, so a still-looping file would hold a ticker open on an idle screen
  — and would stop any widget test's `pumpAndSettle` from ever settling,
  which would have broken two existing tests.
- The `Lottie.asset` widget is held in a field, not built inside the
  `AnimatedBuilder`. Constructing it per frame re-reads and re-decodes 200 KB
  of JSON sixty times a second.
- `frameRate: FrameRate(30)` matches the file, so a 120 Hz panel stops
  re-rasterising four identical frames.

`_smokeFade` survives as the rider's single fade-out: the puffs now live
inside the file, so one opacity takes bike, smoke and road out together.

The file's own `Road` layer is **kept, not hidden**. It is a short trim-path
dash that travels with the bike, so it reads as speed lines under the wheels
rather than as a fixed road the card has to line up with — nothing to align,
nothing to suppress.

## Licensing

"Delivery Boy" by **Animo Arts** on LottieFiles, under the Lottie Simple
License (FL 9.13.21): commercial use permitted, attribution encouraged but
not required. The screen carries `Animation by Animo Arts on LottieFiles` in
11 pt secondary text directly under the card — credited, but not competing
with the build identity. The new test asserts that line exists, because it is
exactly the sort of thing a later layout tidy-up deletes without noticing.

## Verified, and how

Rendered the page to goldens at five points on the timeline (300 / 700 / 1200
/ 1800 / 2600 ms) in both themes and looked at them: the rider enters from
off-frame, sits correctly on the bottom edge of the card at 170 px, clips
cleanly against the card's rounded corners, is gone by 1800 ms and the build
identity is up at 2600 ms. The colour illustration reads on both the light
and the dark card. Those goldens were a scratch harness and are **not**
committed — the suite has no golden baseline and this was not the task to
start one.

The new test also loads `assets/anim/delivery_boy.json` through
`AssetLottie` and asserts it decodes to a composition with a non-zero
duration. That is the part worth having: a wrong asset path fails only at
runtime and fails *quietly* — the card simply renders empty and the screen
still "works".

## API I need from web-agent

- none

## Needs doing next

- [ ] Eyes on it on a real device — there is still no Android SDK on this
      machine, so the APK size delta (expected ≈ +40 KB asset + the lottie
      package's Dart) is a calculation, not a measurement. (mobile-agent, next
      time a build host is available.)
- [ ] Version bump + release are deliberately **not** in this change.
      (whoever cuts the release)

## Blocked on

- Nothing.
