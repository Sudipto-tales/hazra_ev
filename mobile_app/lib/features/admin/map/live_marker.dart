import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';

/// What one live marker should look like — derived, pure, and unit-tested.
///
/// The marker is **two independent channels**, and keeping them independent is
/// the point of this file:
///
/// * **The marker's colour is state.** Moving, stopped, alert, last-known,
///   stale. Five values, no more, all from [AppColors]. This is [tone], and it
///   is what makes "green is moving, red needs me" readable at a glance.
/// * **Glyph is `activity`.** Mapped 1:1 from the server's enum (API plan
///   §3.14) and from nothing else. There is deliberately no client-side
///   recombination of `status` + `movement` + `locationHealth` here — that
///   duplication is the exact thing `activity` was specced to delete, and a
///   second copy of the server's precedence table would drift from it within
///   a release.
///
/// **Identity moved off the marker and onto the line.** It used to be that
/// *everything* was state — the trail was drawn in [tone] too, so two people
/// in the same state had the same-coloured trail and you could not tell whose
/// was whose where they crossed. Identity now lives on the **trail**, in a
/// stable per-employee hue ([AppColors.employeeColor]); the marker keeps
/// state. So: **line answers "whose?", marker answers "doing what?"** — and the
/// map's legend names the line colours. The marker is deliberately *not*
/// tinted with the identity hue: overloading the one at-a-glance status signal
/// with a second meaning is the exact trap this split exists to avoid.
///
/// Conflating the marker's two channels is how a map ends up with eight colours
/// nobody can name. An admin learns the five states in a second; learning
/// sixteen colour-glyph combinations takes a legend, and nobody reads legends
/// on a map they glance at.

/// The colour channel. Five states, and every one of them is answerable.
enum LiveMarkerTone {
  /// Under GPS and moving.
  moving,

  /// Settled — a stop, or stationary without a stop row yet.
  stopped,

  /// Something needs a human: a long stop, or GPS switched off.
  alert,

  /// Nothing is being reported because nothing is open — on break, or the day
  /// is done. The marker is the *last known* position and says so.
  lastKnown,

  /// The fix is older than `offlineThresholdMinutes`. Overrides every tone
  /// above, because how old the data is matters more than what it says.
  stale,
}

extension LiveMarkerToneX on LiveMarkerTone {
  Color get color => switch (this) {
        LiveMarkerTone.moving => AppColors.success,
        LiveMarkerTone.stopped => AppColors.warning,
        LiveMarkerTone.alert => AppColors.danger,
        LiveMarkerTone.lastKnown => AppColors.textTertiary,
        LiveMarkerTone.stale => AppColors.statusOffline,
      };

  String get label => switch (this) {
        LiveMarkerTone.moving => 'Moving',
        LiveMarkerTone.stopped => 'Stopped',
        LiveMarkerTone.alert => 'Needs attention',
        LiveMarkerTone.lastKnown => 'Last known',
        LiveMarkerTone.stale => 'Stale fix',
      };
}

@immutable
class LiveMarker {
  const LiveMarker._({
    required this.member,
    required this.fix,
    required this.now,
    required this.tone,
    required this.glyph,
    required this.fixAge,
    required this.stale,
    required this.dwellFraction,
    required this.longStop,
  });

  /// Everything a marker needs, or `null` when this member does not belong on
  /// the map at all.
  ///
  /// Two reasons to be absent, and they are different: `activity` of
  /// `not_started` means there is no day to draw (§3.14's mapping note says so
  /// explicitly), and a missing `lastFix` means there is no coordinate to draw
  /// it at. Neither is an error and neither gets a placeholder — an invented
  /// position on a tracking map is worse than an absence.
  static LiveMarker? of(
    TeamMember member, {
    required TrackingConfig config,
    required DateTime now,
  }) {
    final LocationLog? fix = member.lastFix;
    if (fix == null) return null;
    if (member.activity == ActivityState.notStarted) return null;
    if (member.activity == null && member.status == WorkStatus.notStarted) {
      // v7 fallback: no `activity` to read, so the one enum the server does
      // send answers the same question for this single case.
      return null;
    }

    final Duration age = now.difference(fix.recordedAt);
    // Negative means the device clock runs ahead of this one. Treat it as
    // fresh rather than as a 40-minute-old fix from the future.
    final Duration fixAge = age.isNegative ? Duration.zero : age;
    final bool stale =
        fixAge.inSeconds > config.offlineThresholdMinutes * 60;

    final Duration? dwell = member.openStopDuration;
    final bool longStop = member.isLongStop(config.longStopThresholdMinutes);
    final double dwellFraction = dwell == null ||
            config.longStopThresholdMinutes <= 0
        ? 0
        : (dwell.inSeconds / (config.longStopThresholdMinutes * 60))
            .clamp(0.0, 1.0);

    return LiveMarker._(
      member: member,
      fix: fix,
      now: now,
      tone: _tone(member, stale: stale, longStop: longStop),
      glyph: _glyph(member.activity),
      fixAge: fixAge,
      stale: stale,
      dwellFraction: dwellFraction,
      longStop: longStop,
    );
  }

  final TeamMember member;
  final LocationLog fix;

  /// The instant this marker was derived against. Held so [ageLabel] and
  /// [fixAge] cannot disagree.
  final DateTime now;
  final LiveMarkerTone tone;
  final IconData glyph;

  /// How old the fix is **right now**. Shown on every marker, always: a watch
  /// is a request for freshness, not a claim of it, and entering live mode
  /// takes up to one drain interval (§3.11). A marker that looks live because
  /// somebody pressed a button is the one failure mode this screen must not
  /// have.
  final Duration fixAge;

  /// Older than `offlineThresholdMinutes`. Greys the marker, drops it to
  /// [opacity] and is the one rule no other channel may override.
  final bool stale;

  /// How far the current stop has run towards `longStopThresholdMinutes`,
  /// `0..1`. Drawn as a ring that fills, so a long stop is visible *before* it
  /// alerts rather than appearing fully formed at the threshold.
  final double dwellFraction;

  final bool longStop;

  String get employeeId => member.employee.id;

  double get latitude => fix.latitude;
  double get longitude => fix.longitude;

  /// 0.6 when stale. Deliberately not 0: a disappearing marker reads as "they
  /// went home", a faded one reads as "I do not know where they are", and only
  /// the second is true.
  double get opacity => stale ? 0.6 : 1;

  /// `null` means *unknown*, and unknown is never drawn as north (§3.11).
  ///
  /// Also null on a stale marker: a direction-of-travel notch on a 40-minute
  /// old fix claims the employee is still going that way, which is precisely
  /// the kind of inference this screen refuses to make.
  double? get bearingDeg => stale ? null : fix.headingDeg;

  /// `activity` as the server derived it, or null on a server below config
  /// version 9 — in which case the glyph is neutral and only the colour and
  /// the age carry information.
  ActivityState? get activity => member.activity;

  String get ageLabel => Fmt.freshness(fix.recordedAt, now: now);

  /// What the roster row says under the name. Falls back to the `status` label
  /// — one enum, the same one the dashboard roster has always rendered — when
  /// the server has no `activity` to offer.
  String get stateLabel => activity?.label ?? member.status.label;

  static LiveMarkerTone _tone(
    TeamMember member, {
    required bool stale,
    required bool longStop,
  }) {
    // The honesty rule outranks everything: a fix nobody has heard from is
    // grey whatever it last said it was doing.
    if (stale) return LiveMarkerTone.stale;

    final ActivityState? activity = member.activity;
    if (activity == null) {
      // v7 server. `status` is a single enum, not a recombination of three, so
      // reading it is not a local re-implementation of §3.14 — it is the
      // colour mapping the roster already uses, reused.
      return switch (member.status) {
        WorkStatus.working => LiveMarkerTone.moving,
        WorkStatus.idle =>
          longStop ? LiveMarkerTone.alert : LiveMarkerTone.stopped,
        WorkStatus.locationUnavailable || WorkStatus.offline =>
          LiveMarkerTone.alert,
        // `paused` groups with these: no session is open, so the pin is a last
        // known position and not a live one. The server never sends it as
        // `status` today (§3.14 keeps that distinction in `activity`), so this
        // arm is defensive — but it is the honest reading if it ever does.
        WorkStatus.paused ||
        WorkStatus.ended ||
        WorkStatus.notStarted =>
          LiveMarkerTone.lastKnown,
      };
    }

    return switch (activity) {
      ActivityState.moving => LiveMarkerTone.moving,
      ActivityState.idle => LiveMarkerTone.stopped,
      ActivityState.stopped =>
        longStop ? LiveMarkerTone.alert : LiveMarkerTone.stopped,
      ActivityState.locationUnavailable ||
      ActivityState.offline =>
        LiveMarkerTone.alert,
      ActivityState.paused ||
      ActivityState.ended ||
      ActivityState.notStarted =>
        LiveMarkerTone.lastKnown,
    };
  }

  /// One glyph per `activity` value, 1:1, and a neutral disc when there is no
  /// `activity` to read. The neutral case is not a guess dressed up as an
  /// icon: a v7 server genuinely does not know, and a filled dot says exactly
  /// that while the colour and the age keep working.
  static IconData _glyph(ActivityState? activity) => switch (activity) {
        ActivityState.moving => Icons.two_wheeler_rounded,
        ActivityState.stopped => Icons.timer_outlined,
        ActivityState.idle => Icons.circle_outlined,
        ActivityState.locationUnavailable => Icons.gps_off_rounded,
        ActivityState.offline => Icons.cloud_off_rounded,
        ActivityState.paused => Icons.pause_rounded,
        ActivityState.ended => Icons.flag_rounded,
        ActivityState.notStarted => Icons.remove_rounded,
        null => Icons.circle,
      };
}

/// One marker's slide from where it was drawn to where its new fix puts it.
///
/// A marker that teleports reads as a rendering bug; the same move over
/// [MapConfig.liveMarkerMotion] with an ease-out reads as movement, which is
/// what it is. Kept out of the widget so the awkward half — bearing — can be
/// tested without a map.
@immutable
class MarkerMotion {
  const MarkerMotion({
    required this.fromLat,
    required this.fromLng,
    required this.toLat,
    required this.toLng,
    this.fromBearing,
    this.toBearing,
  });

  /// A marker appearing for the first time has nowhere to come from, so it
  /// starts where it is rather than flying in from a previous employee's
  /// position.
  factory MarkerMotion.still(LiveMarker marker) => MarkerMotion(
        fromLat: marker.latitude,
        fromLng: marker.longitude,
        toLat: marker.latitude,
        toLng: marker.longitude,
        fromBearing: marker.bearingDeg,
        toBearing: marker.bearingDeg,
      );

  final double fromLat;
  final double fromLng;
  final double toLat;
  final double toLng;
  final double? fromBearing;
  final double? toBearing;

  bool get isStill => fromLat == toLat && fromLng == toLng;

  double latAt(double t) => fromLat + (toLat - fromLat) * t;
  double lngAt(double t) => fromLng + (toLng - fromLng) * t;

  /// Bearing part-way through the move, **the short way round the circle**.
  ///
  /// 350° → 10° is a 20° nudge clockwise, not a 340° spin anticlockwise. Null
  /// at either end short-circuits: there is no interpolating to or from
  /// "unknown", and inventing an intermediate heading would be drawing data
  /// that does not exist.
  double? bearingAt(double t) {
    final double? to = toBearing;
    if (to == null) return null;
    final double? from = fromBearing;
    if (from == null) return to;
    final double delta = ((to - from + 540) % 360) - 180;
    final double value = (from + delta * t) % 360;
    return value < 0 ? value + 360 : value;
  }
}

/// Grid-bucketed clustering — nine phones in one depot must not be nine
/// overlapping discs.
///
/// A grid, not k-means: at a glance an admin needs "there are nine of them
/// over there", and a grid gives that in one pass with no tuning, no
/// iteration and a stable answer frame to frame (a wobbling cluster centre is
/// worse than a slightly off one). Cell size halves with every zoom level, so
/// the cluster dissolves as the admin zooms in and is gone entirely above
/// [MapConfig.liveClusterMaxZoom].
@immutable
class MarkerCluster {
  const MarkerCluster({
    required this.latitude,
    required this.longitude,
    required this.markers,
  });

  final double latitude;
  final double longitude;
  final List<LiveMarker> markers;

  bool get isSingle => markers.length == 1;

  LiveMarker get single => markers.first;

  /// The loudest thing in the cluster decides its colour: one person who needs
  /// attention inside a group of nine must not be averaged away.
  LiveMarkerTone get tone {
    const List<LiveMarkerTone> priority = <LiveMarkerTone>[
      LiveMarkerTone.alert,
      LiveMarkerTone.moving,
      LiveMarkerTone.stopped,
      LiveMarkerTone.stale,
      LiveMarkerTone.lastKnown,
    ];
    for (final LiveMarkerTone t in priority) {
      if (markers.any((LiveMarker m) => m.tone == t)) return t;
    }
    return LiveMarkerTone.lastKnown;
  }

  static List<MarkerCluster> group(
    List<LiveMarker> markers, {
    required double zoom,
    required double maxClusterZoom,
  }) {
    if (zoom > maxClusterZoom || markers.length < 2) {
      return markers
          .map((LiveMarker m) => MarkerCluster(
                latitude: m.latitude,
                longitude: m.longitude,
                markers: <LiveMarker>[m],
              ))
          .toList(growable: false);
    }

    // ~0.044° at z12, roughly 5 km — a depot, a market, a town centre.
    final double cell = 180 / math.pow(2, zoom);
    final Map<String, List<LiveMarker>> buckets = <String, List<LiveMarker>>{};

    for (final LiveMarker m in markers) {
      final String key =
          '${(m.latitude / cell).floor()}:${(m.longitude / cell).floor()}';
      buckets.putIfAbsent(key, () => <LiveMarker>[]).add(m);
    }

    return buckets.values.map((List<LiveMarker> group) {
      double lat = 0;
      double lng = 0;
      for (final LiveMarker m in group) {
        lat += m.latitude;
        lng += m.longitude;
      }
      return MarkerCluster(
        latitude: lat / group.length,
        longitude: lng / group.length,
        markers: group,
      );
    }).toList(growable: false);
  }
}
