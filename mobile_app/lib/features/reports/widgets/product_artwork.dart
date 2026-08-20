import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';

/// Product photo stand-in.
///
/// The catalogue carries an image set **per colour** (`ProductColor.imageUrls`),
/// but this build ships no assets and makes no network calls, so the vehicle is
/// painted instead — in the selected colour, from [variant] different angles.
/// The behaviour the seller sees is the real one: change the colour, the picture
/// changes. Swap the body of [build] for `Image.network(color.imageUrls[variant])`
/// when the catalogue is served.
class ProductArtwork extends StatelessWidget {
  const ProductArtwork({
    super.key,
    required this.category,
    required this.argb,
    this.variant = 0,
    this.height = 150,
    this.radius = Radii.md,
    this.padding = Insets.md,
  });

  final ProductCategory category;

  /// Body colour, straight from `ProductColor.argb`.
  final int argb;

  /// Which frame of that colour's set: 0 side, 1 three-quarter, 2 front.
  final int variant;

  final double height;
  final double radius;
  final double padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: double.infinity,
      padding: EdgeInsets.all(padding),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: context.isDark
              ? const <Color>[AppColors.surfaceAltDark, AppColors.backgroundDark]
              : const <Color>[AppColors.surface, AppColors.surfaceAlt],
        ),
        border: Border.all(color: context.lineColor),
      ),
      child: CustomPaint(
        painter: _ProductPainter(
          category: category,
          body: Color(argb),
          variant: variant % 3,
          tyre: context.isDark
              ? const Color(0xFF0B1120)
              : const Color(0xFF334155),
          outline: context.isDark
              ? Colors.white.withValues(alpha: 0.22)
              : Colors.black.withValues(alpha: 0.18),
          shadow: Colors.black.withValues(alpha: context.isDark ? 0.35 : 0.12),
        ),
        child: const SizedBox.expand(),
      ),
    );
  }
}

class _ProductPainter extends CustomPainter {
  const _ProductPainter({
    required this.category,
    required this.body,
    required this.variant,
    required this.tyre,
    required this.outline,
    required this.shadow,
  });

  final ProductCategory category;
  final Color body;
  final int variant;
  final Color tyre;
  final Color outline;
  final Color shadow;

  @override
  void paint(Canvas canvas, Size size) {
    // Work in a 100 × 60 box and scale up, so every shape below is written in
    // one set of proportions regardless of the tile it lands in.
    const double vw = 100;
    const double vh = 60;
    final double scale = math.min(size.width / vw, size.height / vh);
    canvas.save();
    canvas.translate(
      (size.width - vw * scale) / 2,
      (size.height - vh * scale) / 2,
    );
    canvas.scale(scale);

    // Ground shadow — the only thing shared by every category and angle.
    canvas.drawOval(
      Rect.fromCenter(
        center: const Offset(vw / 2, 55),
        width: variant == 2 ? 34 : 76,
        height: 6,
      ),
      Paint()..color = shadow,
    );

    final bool front = variant == 2;
    switch (category) {
      case ProductCategory.scooty:
        if (front) {
          _scootyFront(canvas);
        } else {
          _scootySide(canvas);
        }
      case ProductCategory.bike:
        if (front) {
          _bikeFront(canvas);
        } else {
          _bikeSide(canvas);
        }
      case ProductCategory.bicycle:
        if (front) {
          _bikeFront(canvas);
        } else {
          _bicycleSide(canvas);
        }
      case ProductCategory.others:
        _crate(canvas);
    }

    canvas.restore();
  }

  // ------------------------------------------------------------------ paints

  Paint get _bodyFill => Paint()..color = body;

  Paint get _bodyLine => Paint()
    ..color = outline
    ..style = PaintingStyle.stroke
    ..strokeWidth = 0.9
    ..strokeJoin = StrokeJoin.round;

  Paint get _tyreFill => Paint()..color = tyre;

  Paint get _metal => Paint()
    ..color = tyre.withValues(alpha: 0.85)
    ..style = PaintingStyle.stroke
    ..strokeWidth = 1.8
    ..strokeCap = StrokeCap.round;

  /// Glossy top edge so a white body still reads as a body.
  Paint get _gloss => Paint()..color = Colors.white.withValues(alpha: 0.35);

  void _wheel(Canvas canvas, Offset c, double r, {double rim = 0.45}) {
    canvas.drawCircle(c, r, _tyreFill);
    canvas.drawCircle(
      c,
      r * rim,
      Paint()..color = Colors.white.withValues(alpha: 0.55),
    );
    canvas.drawCircle(c, r * 0.16, Paint()..color = tyre);
  }

  void _shape(Canvas canvas, Path p) {
    canvas.drawPath(p, _bodyFill);
    canvas.drawPath(p, _bodyLine);
  }

  // ------------------------------------------------------------- scooty

  void _scootySide(Canvas canvas) {
    // Three-quarter view is the side view with a wider rear and a visible
    // handlebar sweep, which is enough to read as a different photo.
    final bool threeQuarter = variant == 1;
    const double y = 46; // wheel axle line
    const double r = 8.5;

    _wheel(canvas, const Offset(22, y), r);
    _wheel(canvas, const Offset(78, y), r);

    // Deck + rear body.
    final Path shell = Path()
      ..moveTo(14, 40)
      ..lineTo(34, 40)
      ..lineTo(40, 36)
      ..lineTo(58, 36)
      ..cubicTo(66, 36, 72, 32, 74, 26)
      ..lineTo(88, 26)
      ..cubicTo(93, 26, 95, 30, 93, 36)
      ..cubicTo(91, 42, 86, 43, 82, 40)
      ..lineTo(70, 40)
      ..lineTo(58, 44)
      ..lineTo(30, 44)
      ..cubicTo(22, 44, 14, 44, 14, 40)
      ..close();
    _shape(canvas, shell);

    // Seat.
    final Path seat = Path()
      ..moveTo(58, 26)
      ..cubicTo(66, 22, 80, 21, 90, 23)
      ..cubicTo(90, 27, 84, 28, 74, 28)
      ..cubicTo(66, 28, 60, 28, 58, 26)
      ..close();
    canvas.drawPath(seat, Paint()..color = tyre.withValues(alpha: 0.9));

    // Front apron, stem, handlebar.
    final Path apron = Path()
      ..moveTo(16, 40)
      ..cubicTo(12, 34, 12, 26, 18, 20)
      ..lineTo(26, 20)
      ..cubicTo(24, 28, 24, 34, 26, 40)
      ..close();
    _shape(canvas, apron);
    canvas.drawLine(const Offset(21, 20), const Offset(24, 12), _metal);
    canvas.drawLine(
      Offset(threeQuarter ? 14 : 17, 11),
      const Offset(31, 13),
      _metal,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(15, 22, 9, 5),
        const Radius.circular(1.5),
      ),
      _gloss,
    );

    // Gloss highlight on the rear panel.
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(76, 29, 14, 3),
        const Radius.circular(1.5),
      ),
      _gloss,
    );
  }

  void _scootyFront(Canvas canvas) {
    _wheel(canvas, const Offset(50, 46), 7.5, rim: 0.4);

    final Path shell = Path()
      ..moveTo(38, 40)
      ..cubicTo(34, 30, 36, 20, 42, 16)
      ..lineTo(58, 16)
      ..cubicTo(64, 20, 66, 30, 62, 40)
      ..close();
    _shape(canvas, shell);

    // Handlebar + headlamp.
    canvas.drawLine(const Offset(32, 14), const Offset(68, 14), _metal);
    canvas.drawOval(
      Rect.fromCenter(center: const Offset(50, 22), width: 14, height: 8),
      _gloss,
    );
    canvas.drawOval(
      Rect.fromCenter(center: const Offset(50, 32), width: 18, height: 5),
      Paint()..color = Colors.white.withValues(alpha: 0.2),
    );
  }

  // --------------------------------------------------------------- bike

  void _bikeSide(Canvas canvas) {
    const double y = 44;
    const double r = 11;

    _wheel(canvas, const Offset(20, y), r, rim: 0.62);
    _wheel(canvas, const Offset(80, y), r, rim: 0.62);

    // Frame.
    canvas.drawLine(const Offset(30, 40), const Offset(46, 26), _metal);
    canvas.drawLine(const Offset(46, 26), const Offset(70, 30), _metal);
    canvas.drawLine(const Offset(70, 30), const Offset(76, 42), _metal);
    canvas.drawLine(const Offset(46, 26), const Offset(52, 40), _metal);

    // Tank / battery box.
    final Path tank = Path()
      ..moveTo(38, 26)
      ..cubicTo(44, 20, 56, 19, 64, 22)
      ..lineTo(66, 30)
      ..cubicTo(56, 32, 46, 32, 40, 30)
      ..close();
    _shape(canvas, tank);

    // Seat + tail.
    final Path tail = Path()
      ..moveTo(64, 22)
      ..cubicTo(72, 20, 84, 19, 90, 21)
      ..cubicTo(88, 25, 78, 26, 68, 26)
      ..close();
    canvas.drawPath(tail, Paint()..color = tyre.withValues(alpha: 0.9));

    // Fork, handlebar, headlamp cowl.
    canvas.drawLine(const Offset(20, 44), const Offset(30, 18), _metal);
    canvas.drawLine(const Offset(24, 15), const Offset(38, 19), _metal);
    final Path cowl = Path()
      ..moveTo(26, 20)
      ..cubicTo(22, 24, 22, 30, 26, 32)
      ..lineTo(32, 28)
      ..cubicTo(32, 24, 30, 20, 26, 20)
      ..close();
    _shape(canvas, cowl);

    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(44, 22, 16, 3),
        const Radius.circular(1.5),
      ),
      _gloss,
    );
  }

  void _bikeFront(Canvas canvas) {
    _wheel(canvas, const Offset(50, 44), 10, rim: 0.55);
    canvas.drawLine(const Offset(44, 36), const Offset(44, 18), _metal);
    canvas.drawLine(const Offset(56, 36), const Offset(56, 18), _metal);
    canvas.drawLine(const Offset(32, 16), const Offset(68, 16), _metal);

    final Path cowl = Path()
      ..moveTo(42, 18)
      ..cubicTo(38, 22, 38, 30, 44, 34)
      ..lineTo(56, 34)
      ..cubicTo(62, 30, 62, 22, 58, 18)
      ..close();
    _shape(canvas, cowl);
    canvas.drawOval(
      Rect.fromCenter(center: const Offset(50, 24), width: 12, height: 7),
      _gloss,
    );
  }

  // ------------------------------------------------------------ bicycle

  void _bicycleSide(Canvas canvas) {
    const double y = 42;
    const double r = 13;
    final Paint spoke = Paint()
      ..color = tyre.withValues(alpha: 0.5)
      ..strokeWidth = 0.5;

    for (final double cx in <double>[20, 80]) {
      canvas.drawCircle(Offset(cx, y), r, Paint()
        ..color = tyre
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2);
      for (int i = 0; i < 8; i++) {
        final double a = i * math.pi / 4;
        canvas.drawLine(
          Offset(cx, y),
          Offset(cx + math.cos(a) * r, y + math.sin(a) * r),
          spoke,
        );
      }
    }

    // Diamond frame, painted in the body colour so the colour choice reads.
    final Paint frame = Paint()
      ..color = body
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.4
      ..strokeCap = StrokeCap.round;
    final Path f = Path()
      ..moveTo(20, 42)
      ..lineTo(44, 42)
      ..lineTo(56, 22)
      ..lineTo(36, 22)
      ..close()
      ..moveTo(44, 42)
      ..lineTo(80, 42)
      ..moveTo(56, 22)
      ..lineTo(80, 42)
      ..moveTo(36, 22)
      ..lineTo(20, 42);
    canvas.drawPath(f, frame);

    // Battery on the down tube — this is an e-bike.
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(30, 28, 16, 6),
        const Radius.circular(2),
      ),
      Paint()..color = tyre.withValues(alpha: 0.9),
    );

    // Fork, handlebar, saddle.
    canvas.drawLine(const Offset(20, 42), const Offset(34, 20), _metal);
    canvas.drawLine(const Offset(30, 17), const Offset(40, 20), _metal);
    canvas.drawLine(const Offset(56, 22), const Offset(58, 14), _metal);
    final Path saddle = Path()
      ..moveTo(51, 14)
      ..cubicTo(56, 11, 66, 11, 68, 14)
      ..cubicTo(64, 17, 55, 17, 51, 14)
      ..close();
    canvas.drawPath(saddle, Paint()..color = tyre);

    // Crank.
    canvas.drawCircle(const Offset(44, 42), 3, _metal);
  }

  // ------------------------------------------------------------- others

  void _crate(Canvas canvas) {
    final Path box = Path()
      ..moveTo(28, 44)
      ..lineTo(28, 20)
      ..lineTo(50, 12)
      ..lineTo(72, 20)
      ..lineTo(72, 44)
      ..lineTo(50, 52)
      ..close();
    _shape(canvas, box);

    // Top face + front seam so the box reads as a solid.
    final Path top = Path()
      ..moveTo(28, 20)
      ..lineTo(50, 12)
      ..lineTo(72, 20)
      ..lineTo(50, 28)
      ..close();
    canvas.drawPath(top, _gloss);
    canvas.drawLine(const Offset(50, 28), const Offset(50, 52), _bodyLine);

    // Bolt.
    final Path bolt = Path()
      ..moveTo(46, 30)
      ..lineTo(56, 30)
      ..lineTo(50, 38)
      ..lineTo(58, 38)
      ..lineTo(42, 48)
      ..lineTo(48, 38)
      ..lineTo(42, 38)
      ..close();
    canvas.drawPath(bolt, Paint()..color = AppColors.warning);
  }

  @override
  bool shouldRepaint(_ProductPainter old) =>
      old.category != category ||
      old.body != body ||
      old.variant != variant ||
      old.tyre != tyre ||
      old.outline != outline ||
      old.shadow != shadow;
}
