import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import 'live_marker.dart';
import 'map_chrome.dart';

/// One employee on the live map.
///
/// Same teardrop as the historical route screen ([MapPin]) — same white
/// casing, same shadow, same tip-on-the-coordinate — with three things added
/// that only a *live* marker needs:
///
/// 1. a [BearingNotch], when and only when the fix carries a heading;
/// 2. a dwell ring that fills as a stop approaches the long-stop threshold;
/// 3. the fix's real age, under the pin, on every single marker.
///
/// (3) is not decoration. The device only learns it is being watched when it
/// next posts a batch, so entering live mode can take up to one drain interval
/// (API plan §3.11). A marker that implies freshness because an admin opened a
/// screen would be the one genuinely dangerous thing this map could do.
class LivePin extends StatelessWidget {
  const LivePin({
    super.key,
    required this.marker,
    required this.bearingDeg,
    required this.selected,
    required this.width,
  });

  final LiveMarker marker;

  /// The *animated* bearing, which is why it is passed in rather than read off
  /// [marker]: mid-slide the notch points part-way round. Null is null all the
  /// way down — no rotation, ever.
  final double? bearingDeg;

  final bool selected;
  final double width;

  /// Pin height as a fraction of width — the teardrop's own aspect.
  static const double heightFactor = 1.32;

  /// Room under the tip for the age chip.
  static const double ageBandHeight = 15;

  static double pinHeight(double width) => width * heightFactor;

  static double totalHeight(double width) =>
      pinHeight(width) + ageBandHeight + 2;

  @override
  Widget build(BuildContext context) {
    final Color color = marker.tone.color;
    final double height = pinHeight(width);

    return Opacity(
      opacity: marker.opacity,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          SizedBox(
            width: width,
            height: height,
            child: MapPin(
              color: color,
              icon: marker.glyph,
              width: width,
              height: height,
              borderColor: selected ? AppColors.primary : Colors.white,
              borderWidth: selected ? 3 : 2,
              overlay: Align(
                alignment: Alignment.topCenter,
                child: SizedBox(
                  // The head is the top width x width square of the pin, so
                  // this box is centred on the glyph disc — which is what lets
                  // the notch rotate about the right point.
                  width: width,
                  height: width,
                  child: Stack(
                    children: <Widget>[
                      if (marker.dwellFraction > 0)
                        Positioned.fill(
                          child: CustomPaint(
                            painter: _DwellRingPainter(
                              fraction: marker.dwellFraction,
                              color: marker.longStop
                                  ? AppColors.danger
                                  : Colors.white,
                            ),
                          ),
                        ),
                      if (bearingDeg != null)
                        Positioned.fill(
                          child: BearingNotch(
                            bearingDeg: bearingDeg!,
                            color: Colors.white,
                            size: width * 0.26,
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          _AgeChip(label: marker.ageLabel, stale: marker.stale),
        ],
      ),
    );
  }
}

/// A small triangle on the rim of the disc, rotated to the direction of
/// travel.
///
/// **The notch rotates; the glyph never does.** A vehicle icon spun to 143°
/// reads as a broken asset, and at 180° it reads as a vehicle driving
/// backwards — the glyph's job is "what", and rotating it corrupts that to say
/// something about "where". So "where" gets its own mark, and that mark is the
/// only thing that turns.
///
/// Existence is the contract: this widget is **not built at all** when the fix
/// has no heading. Null means unknown, not north (§3.11), and a notch pointing
/// up on every parked phone would render missing data as data.
class BearingNotch extends StatelessWidget {
  const BearingNotch({
    super.key,
    required this.bearingDeg,
    required this.color,
    required this.size,
  });

  /// Degrees clockwise from north. Non-nullable by design: the caller decides
  /// whether there is a bearing at all, so this widget can never be asked to
  /// draw an unknown one.
  final double bearingDeg;

  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Transform.rotate(
      // Rotating about the box centre, which the caller has aligned with the
      // glyph disc. 0° therefore puts the notch straight up — north — which is
      // exactly what a heading of 0 means.
      angle: bearingDeg * math.pi / 180,
      child: Align(
        alignment: Alignment.topCenter,
        child: CustomPaint(
          size: Size(size, size * 0.8),
          painter: _NotchPainter(color: color),
        ),
      ),
    );
  }
}

class _NotchPainter extends CustomPainter {
  const _NotchPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final Path path = Path()
      ..moveTo(size.width / 2, 0)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawShadow(path, Colors.black, 1, false);
    canvas.drawPath(path, Paint()..color = color);
  }

  @override
  bool shouldRepaint(_NotchPainter old) => old.color != color;
}

/// The ring that fills as a stop runs on.
///
/// It exists so a long stop is visible *before* it alerts: a ring three
/// quarters round is an admin's cue to look, half a dozen minutes before the
/// marker turns red on its own. Drawn from 12 o'clock clockwise, like every
/// progress ring anyone has ever seen.
class _DwellRingPainter extends CustomPainter {
  const _DwellRingPainter({required this.fraction, required this.color});

  final double fraction;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final Offset centre = Offset(size.width / 2, size.height / 2);
    final double radius = size.width / 2 - 4.5;
    if (radius <= 0) return;

    final Rect rect = Rect.fromCircle(center: centre, radius: radius);

    canvas.drawArc(
      rect,
      0,
      math.pi * 2,
      false,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2
        ..color = Colors.white.withValues(alpha: 0.35),
    );
    canvas.drawArc(
      rect,
      -math.pi / 2,
      math.pi * 2 * fraction.clamp(0.0, 1.0),
      false,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.4
        ..strokeCap = StrokeCap.round
        ..color = color,
    );
  }

  @override
  bool shouldRepaint(_DwellRingPainter old) =>
      old.fraction != fraction || old.color != color;
}

class _AgeChip extends StatelessWidget {
  const _AgeChip({required this.label, required this.stale});

  final String label;
  final bool stale;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
      decoration: BoxDecoration(
        color: stale
            ? AppColors.statusOffline
            : Colors.white.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(Radii.pill),
        boxShadow: const <BoxShadow>[
          BoxShadow(color: Color(0x22000000), blurRadius: 2, offset: Offset(0, 1)),
        ],
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.clip,
        softWrap: false,
        style: TextStyle(
          fontSize: 9.5,
          height: 1.2,
          fontWeight: FontWeight.w600,
          color: stale ? Colors.white : AppColors.textSecondary,
        ),
      ),
    );
  }
}

/// Several people in one place, collapsed to a count.
class ClusterBadge extends StatelessWidget {
  const ClusterBadge({
    super.key,
    required this.count,
    required this.tone,
    required this.size,
  });

  final int count;
  final LiveMarkerTone tone;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: tone.color,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2.5),
        boxShadow: const <BoxShadow>[
          BoxShadow(color: Color(0x33000000), blurRadius: 4, offset: Offset(0, 2)),
        ],
      ),
      child: Text(
        '$count',
        style: TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
          fontSize: size * 0.4,
        ),
      ),
    );
  }
}
