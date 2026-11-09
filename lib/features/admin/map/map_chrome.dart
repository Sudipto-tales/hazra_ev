import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../../../core/theme/dimens.dart';

/// The shared visual language of both map screens.
///
/// Extracted from `route_tile_map.dart` when the live team map landed, because
/// two maps drawing markers two different ways is how an admin stops trusting
/// either of them. The historical route screen and the live team map now use
/// the same teardrop, the same floating buttons and the same pill, and a
/// change to any of them lands on both.
///
/// Nothing here knows about tracking, employees or sessions — it is chrome.

/// The classic map teardrop: a balloon whose tip is the coordinate.
///
/// Drawn rather than composed from an icon font so the tip lands on an exact
/// pixel and the white casing survives over busy tiles. Pair it with
/// `Marker(alignment: Alignment.topCenter)`, or the tip sits half a pin north
/// of where the employee actually stood.
///
/// [overlay] is painted above the balloon and below the glyph disc, in the
/// pin's own coordinate space — that is where the live map puts its dwell ring
/// and its bearing notch, so neither has to re-derive the geometry.
class MapPin extends StatelessWidget {
  const MapPin({
    super.key,
    required this.color,
    required this.icon,
    required this.width,
    required this.height,
    required this.borderColor,
    required this.borderWidth,
    this.overlay,
    this.iconColor,
  });

  final Color color;
  final IconData icon;
  final double width;
  final double height;
  final Color borderColor;
  final double borderWidth;

  /// Painted between the balloon and the glyph. Sized to the whole pin.
  final Widget? overlay;

  /// Defaults to [color] — the glyph sits on the white disc, so it carries the
  /// state colour. Override when the disc itself is tinted.
  final Color? iconColor;

  /// Fraction of [width] taken by the white glyph disc. Exposed so the live
  /// map can line its rings up with the disc rather than guessing.
  static const double discFraction = 0.46;

  @override
  Widget build(BuildContext context) {
    final double head = width / 2;
    final double disc = width * discFraction;

    return SizedBox(
      width: width,
      height: height,
      child: Stack(
        children: <Widget>[
          Positioned.fill(
            child: CustomPaint(
              painter: _PinPainter(
                color: color,
                borderColor: borderColor,
                borderWidth: borderWidth,
              ),
            ),
          ),
          if (overlay != null) Positioned.fill(child: overlay!),
          Positioned(
            left: head - disc / 2,
            top: head - disc / 2,
            width: disc,
            height: disc,
            child: DecoratedBox(
              decoration: const BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: disc * 0.68, color: iconColor ?? color),
            ),
          ),
        ],
      ),
    );
  }
}

class _PinPainter extends CustomPainter {
  const _PinPainter({
    required this.color,
    required this.borderColor,
    required this.borderWidth,
  });

  final Color color;
  final Color borderColor;
  final double borderWidth;

  @override
  void paint(Canvas canvas, Size size) {
    final double w = size.width;
    final double h = size.height;
    final double r = w / 2 - borderWidth / 2;
    final Offset centre = Offset(w / 2, w / 2);

    // Round head, then two curves drawn back down to the tip.
    // latlong2 exports its own `Path`, so the canvas one is named explicitly.
    final ui.Path path = ui.Path()
      ..moveTo(w / 2, h)
      ..cubicTo(w * 0.06, h * 0.66, centre.dx - r, w * 0.86, centre.dx - r,
          centre.dy)
      ..arcToPoint(
        Offset(centre.dx + r, centre.dy),
        radius: Radius.circular(r),
        clockwise: true,
      )
      ..cubicTo(centre.dx + r, w * 0.86, w * 0.94, h * 0.66, w / 2, h)
      ..close();

    canvas.drawShadow(path, Colors.black, 2, false);
    canvas.drawPath(path, Paint()..color = color);
    canvas.drawPath(
      path,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = borderWidth
        ..color = borderColor,
    );
  }

  @override
  bool shouldRepaint(_PinPainter old) =>
      old.color != color ||
      old.borderColor != borderColor ||
      old.borderWidth != borderWidth;
}

/// A single direction arrow drawn along a trail, pointing **north** at rest.
///
/// The caller wraps it in a `Transform.rotate` by the segment's bearing so a
/// there-and-back on one road shows two opposing flows. Deliberately small and
/// hollow (a thin open chevron, not a filled wedge) and carried at the trail's
/// own colour: it is a hint about direction, and it must never shout louder
/// than the stop, start and end dots it travels between.
class TrailArrowIcon extends StatelessWidget {
  const TrailArrowIcon({super.key, required this.color, this.size = 9});

  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: Size(size, size),
      painter: _ChevronPainter(color),
    );
  }
}

class _ChevronPainter extends CustomPainter {
  const _ChevronPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final double w = size.width;
    final double h = size.height;
    // An open "^" pointing up: apex at top-centre, arms down to the corners.
    final ui.Path path = ui.Path()
      ..moveTo(w * 0.12, h * 0.82)
      ..lineTo(w * 0.5, h * 0.18)
      ..lineTo(w * 0.88, h * 0.82);

    // A faint white backing keeps the chevron legible over a same-hue trail and
    // over busy tiles, without the weight of a full casing.
    canvas.drawPath(
      path,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = w * 0.3
        ..strokeJoin = StrokeJoin.round
        ..strokeCap = StrokeCap.round
        ..color = Colors.white.withValues(alpha: 0.7),
    );
    canvas.drawPath(
      path,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = w * 0.18
        ..strokeJoin = StrokeJoin.round
        ..strokeCap = StrokeCap.round
        ..color = color,
    );
  }

  @override
  bool shouldRepaint(_ChevronPainter old) => old.color != color;
}

/// A round floating control over the map — full screen, fit, renderer swap.
class MapIconButton extends StatelessWidget {
  const MapIconButton({
    super.key,
    required this.tooltip,
    required this.icon,
    required this.color,
    required this.onPressed,
  });

  final String tooltip;
  final IconData icon;
  final Color color;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color,
      shape: const CircleBorder(),
      elevation: 1,
      child: IconButton(
        tooltip: tooltip,
        icon: Icon(icon, size: 20),
        onPressed: onPressed,
      ),
    );
  }
}

/// The small rounded label the maps float over their own tiles — "Snapping to
/// roads…", "Live · 8 watched".
class MapPill extends StatelessWidget {
  const MapPill({super.key, required this.child, required this.background});

  final Widget child;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(Radii.pill),
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: Insets.md,
          vertical: Insets.xs,
        ),
        child: DefaultTextStyle.merge(
          style: Theme.of(context).textTheme.bodySmall ?? const TextStyle(),
          child: child,
        ),
      ),
    );
  }
}
