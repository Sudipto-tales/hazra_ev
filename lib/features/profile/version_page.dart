import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/config/brand.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../widgets/app_card.dart';
import '../update/update_gate.dart';
import 'app_info.dart';

/// The screen behind four taps on the version card.
///
/// A rider crosses the card left to right trailing exhaust; when the smoke
/// thins out the build identity fades up in its place, and an update check
/// sits underneath it.
///
/// Everything is painted. There is no Lottie/Rive/SVG dependency and no asset
/// to ship — the app already hand-paints its route trails and product
/// fallbacks, so this follows that precedent rather than adding a package for
/// one screen.
class VersionPage extends StatefulWidget {
  const VersionPage({super.key});

  @override
  State<VersionPage> createState() => _VersionPageState();
}

class _VersionPageState extends State<VersionPage>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2600),
  );

  /// The ride: left edge to right edge, eased so it leans into the exit.
  late final Animation<double> _ride = CurvedAnimation(
    parent: _c,
    curve: const Interval(0, 0.68, curve: Curves.easeInOutCubic),
  );

  /// Smoke lives slightly past the ride and then clears.
  late final Animation<double> _smokeFade = CurvedAnimation(
    parent: _c,
    curve: const Interval(0.62, 0.86, curve: Curves.easeOut),
  );

  /// The reveal only begins once the smoke has gone.
  late final Animation<double> _reveal = CurvedAnimation(
    parent: _c,
    curve: const Interval(0.84, 1, curve: Curves.easeOutBack),
  );

  @override
  void initState() {
    super.initState();
    _c.forward();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Version'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Play again',
            icon: const Icon(Icons.replay_rounded),
            onPressed: () => _c.forward(from: 0),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          AppCard(
            padding: EdgeInsets.zero,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(Radii.lg),
              child: SizedBox(
                height: 190,
                child: AnimatedBuilder(
                  animation: _c,
                  builder: (BuildContext context, _) => CustomPaint(
                    painter: _RidePainter(
                      ride: _ride.value,
                      smoke: 1 - _smokeFade.value,
                      isDark: theme.brightness == Brightness.dark,
                      accent: AppColors.primary,
                    ),
                    child: Center(
                      child: Opacity(
                        // Keyed so a test can assert on *this* opacity: the
                        // update card below also renders an "Version … build …"
                        // string, so matching on the text is ambiguous.
                        key: const ValueKey<String>('version-reveal'),
                        // Clamped: easeOutBack overshoots past 1 and Opacity
                        // asserts on anything outside 0..1.
                        opacity: _reveal.value.clamp(0.0, 1.0),
                        child: Transform.scale(
                          scale: 0.92 + 0.08 * _reveal.value.clamp(0.0, 1.0),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: <Widget>[
                              Text(
                                'Version ${AppInfo.version}',
                                style:
                                    theme.textTheme.headlineSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'build ${AppInfo.build}',
                                style: theme.textTheme.bodySmall,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: Insets.lg),
          const _UpdateCheckCard(),
          const SizedBox(height: Insets.xxl),
          Text(
            '${Brand.product} · built by ${Brand.developer}',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: 4),
          Text(
            Brand.copyright,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

/// Rider, road and exhaust.
///
/// Drawn rather than composed from [Icon]s so the smoke can be anchored to the
/// rear wheel in the same coordinate space as the bike, which is the only way
/// the trail stays attached at every frame.
class _RidePainter extends CustomPainter {
  _RidePainter({
    required this.ride,
    required this.smoke,
    required this.isDark,
    required this.accent,
  });

  /// 0 → off the left edge, 1 → off the right edge.
  final double ride;

  /// 1 → full exhaust, 0 → cleared.
  final double smoke;

  final bool isDark;
  final Color accent;

  @override
  void paint(Canvas canvas, Size size) {
    final double roadY = size.height * 0.72;

    final Paint road = Paint()
      ..color = (isDark ? Colors.white : Colors.black).withValues(alpha: 0.10)
      ..strokeWidth = 2
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(
      Offset(size.width * 0.06, roadY),
      Offset(size.width * 0.94, roadY),
      road,
    );

    // Travel past both edges so the bike enters and leaves off-screen rather
    // than popping into existence at the margin.
    final double x = -70 + ride * (size.width + 140);
    // A gentle bob; it reads as road rather than as a glide.
    final double bob = math.sin(ride * math.pi * 3) * 2.0;
    final double baseY = roadY + bob;

    _paintSmoke(canvas, Offset(x - 20, baseY - 10), size);

    // The rider fades at the very end of the ride so the reveal is not
    // competing with a bike still sliding out of frame.
    final double bikeAlpha = ride < 0.9 ? 1.0 : (1 - (ride - 0.9) / 0.1);
    _paintBike(canvas, Offset(x, baseY), bikeAlpha.clamp(0.0, 1.0));
  }

  void _paintSmoke(Canvas canvas, Offset tail, Size size) {
    if (smoke <= 0.01) return;

    // Deterministic puffs: index-driven, no RNG, so a repaint at the same
    // `ride` draws the same frame. (A random trail flickers when Flutter
    // repaints for an unrelated reason, e.g. a theme change.)
    const int count = 9;
    for (int i = 0; i < count; i++) {
      final double t = i / count;
      // Each puff drifts back from the tailpipe and rises as it ages.
      final double dx = -t * 118;
      final double dy = -t * 30 - math.sin(t * math.pi * 2) * 5;
      final double radius = 3.5 + t * 15;
      final double alpha = (1 - t) * 0.30 * smoke;
      if (alpha <= 0.004) continue;

      canvas.drawCircle(
        tail + Offset(dx, dy),
        radius,
        Paint()
          ..color = (isDark ? Colors.white : Colors.black54)
              .withValues(alpha: alpha)
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 5),
      );
    }
  }

  void _paintBike(Canvas canvas, Offset at, double alpha) {
    if (alpha <= 0.01) return;

    final Color ink = isDark ? Colors.white : const Color(0xFF0F172A);
    final Paint frame = Paint()
      ..color = ink.withValues(alpha: alpha)
      ..strokeWidth = 2.4
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    final Paint body = Paint()..color = accent.withValues(alpha: alpha);
    final Paint tyre = Paint()
      ..color = ink.withValues(alpha: alpha * 0.9)
      ..strokeWidth = 2.6
      ..style = PaintingStyle.stroke;

    const double wheel = 9;
    final Offset rear = at + const Offset(-16, 0);
    final Offset front = at + const Offset(16, 0);

    canvas.drawCircle(rear, wheel, tyre);
    canvas.drawCircle(front, wheel, tyre);

    // Deck and step-through frame — a scooter silhouette, which is what the
    // company actually sells.
    final Path deck = Path()
      ..moveTo(rear.dx, rear.dy - wheel - 3)
      ..lineTo(at.dx - 4, at.dy - wheel - 4)
      ..lineTo(at.dx + 6, at.dy - wheel - 4)
      ..lineTo(front.dx - 1, front.dy - wheel - 11);
    canvas.drawPath(deck, frame);

    // Body panel over the rear wheel.
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(rear.dx - 9, rear.dy - wheel - 11, 20, 9),
        const Radius.circular(3),
      ),
      body,
    );

    // Handlebar.
    canvas.drawLine(
      front + const Offset(-1, -wheel - 11),
      front + const Offset(5, -wheel - 15),
      frame,
    );

    // Rider: head, torso, arm to the bar.
    final Offset head = at + const Offset(-1, -wheel - 29);
    canvas.drawCircle(head, 4.4, body);
    canvas.drawLine(
      head + const Offset(0, 4),
      at + const Offset(-3, -wheel - 13),
      frame,
    );
    canvas.drawLine(
      head + const Offset(1, 6),
      front + const Offset(3, -wheel - 14),
      frame,
    );
  }

  @override
  bool shouldRepaint(_RidePainter old) =>
      old.ride != ride ||
      old.smoke != smoke ||
      old.isDark != isDark ||
      old.accent != accent;
}

/// Manual "check for updates", reusing the controller the gate already owns.
///
/// [UpdateScope] is absent on anything but a live Android build (see
/// `app.dart`), so this degrades to a plain explanation rather than a button
/// that cannot work.
class _UpdateCheckCard extends StatefulWidget {
  const _UpdateCheckCard();

  @override
  State<_UpdateCheckCard> createState() => _UpdateCheckCardState();
}

class _UpdateCheckCardState extends State<_UpdateCheckCard> {
  bool _checked = false;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final controller = UpdateScope.maybeOf(context);

    if (controller == null) {
      return AppCard(
        child: Row(
          children: <Widget>[
            const Icon(Icons.system_update_outlined,
                size: 19, color: AppColors.textSecondary),
            const SizedBox(width: Insets.md),
            Expanded(
              child: Text(
                'Updates are delivered to the Android app only.',
                style: theme.textTheme.bodySmall,
              ),
            ),
          ],
        ),
      );
    }

    return ListenableBuilder(
      listenable: controller,
      builder: (BuildContext context, _) {
        // `requiredUpdate` means the gate is about to take the whole app over,
        // so this card never has to render an "install" flow of its own.
        final bool busy = controller.checking;
        final String status;
        if (busy) {
          status = 'Checking…';
        } else if (controller.error != null) {
          status = controller.error!;
        } else if (controller.requiredUpdate) {
          status = 'An update is available and will be installed now.';
        } else if (_checked) {
          status = 'You are on the latest version.';
        } else {
          status = AppInfo.label;
        }

        return AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Icon(Icons.system_update_outlined,
                      size: 19, color: theme.colorScheme.primary),
                  const SizedBox(width: Insets.md),
                  Expanded(
                    child: Text('App updates',
                        style: theme.textTheme.bodyLarge),
                  ),
                  if (busy)
                    const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  else
                    TextButton(
                      onPressed: () async {
                        await controller.check();
                        if (context.mounted) setState(() => _checked = true);
                      },
                      child: const Text('Check now'),
                    ),
                ],
              ),
              const SizedBox(height: 2),
              Text(
                status,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: controller.error != null ? AppColors.danger : null,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// The version card in Settings. Four taps opens [VersionPage].
///
/// The counter resets if the taps are not consecutive, so a stray tap while
/// scrolling does not accumulate toward the easter egg over a whole session.
class VersionTapCard extends StatefulWidget {
  const VersionTapCard({super.key});

  /// Taps needed, as requested.
  static const int tapsToReveal = 4;

  @override
  State<VersionTapCard> createState() => _VersionTapCardState();
}

class _VersionTapCardState extends State<VersionTapCard> {
  int _taps = 0;
  Timer? _reset;

  @override
  void dispose() {
    _reset?.cancel();
    super.dispose();
  }

  void _tap() {
    _reset?.cancel();
    _taps++;

    if (_taps >= VersionTapCard.tapsToReveal) {
      _taps = 0;
      Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => const VersionPage()),
      );
      return;
    }

    _reset = Timer(const Duration(seconds: 2), () => _taps = 0);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AppCard(
      onTap: _tap,
      child: Row(
        children: <Widget>[
          Icon(Icons.info_outline_rounded,
              size: 19, color: theme.colorScheme.primary),
          const SizedBox(width: Insets.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('App version', style: theme.textTheme.bodyLarge),
                const SizedBox(height: 2),
                // Build identity only. The old string appended "connected to
                // server" / the API address, which is not an employee's
                // business and is the sort of thing that travels in a
                // screenshot.
                Text(AppInfo.label, style: theme.textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
