import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';

import '../../core/config/brand.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../widgets/app_card.dart';
import '../update/update_gate.dart';
import 'app_info.dart';
import 'version_label.dart';

/// The screen behind four taps on the version card.
///
/// A rider crosses the card left to right trailing exhaust; when the exhaust
/// thins out the build identity fades up in its place, and an update check
/// sits underneath it. The narrative is the screen — it is not a centred loop.
///
/// The rider itself is a Lottie file, `assets/anim/delivery_boy.json`: the
/// first asset this app bundles, and the only reason `lottie` is a dependency.
/// That reverses the precedent this screen used to set, deliberately. The
/// painted version was a stick silhouette, and a drawn delivery rider with
/// spinning wheels and puffing exhaust is illustration work, not geometry —
/// there is nothing to compute, so hand-painting it bought nothing but a
/// worse picture. The route trails and product fallbacks are still painted,
/// because their shape comes from runtime data; this frame never does.
///
/// Two controllers, and the split is the whole trick:
///  * [_wheels] repeats the file's own 1.03 s cycle, so the rider stays alive
///    while standing still in its own coordinate space — the animation does
///    not translate.
///  * [_c] runs the three-stage choreography and does the travelling, sliding
///    the looping rider across the card before handing the frame over.
class VersionPage extends StatefulWidget {
  const VersionPage({super.key});

  @override
  State<VersionPage> createState() => _VersionPageState();
}

class _VersionPageState extends State<VersionPage>
    with TickerProviderStateMixin {
  /// The choreography. One pass, replayable from the app bar.
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2600),
  );

  /// The rider's own cycle. 1033 ms is the file's frames 0–31 at 30 fps;
  /// [_onComposition] replaces it with the parsed value so the two can never
  /// drift apart if the asset is ever re-exported.
  late final AnimationController _wheels = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1033),
  );

  /// The ride: left edge to right edge, eased so it leans into the exit.
  late final Animation<double> _ride = CurvedAnimation(
    parent: _c,
    curve: const Interval(0, 0.68, curve: Curves.easeInOutCubic),
  );

  /// The exhaust lives slightly past the ride and then clears. The puffs are
  /// inside the Lottie now, so this fades the whole rider out — bike, smoke
  /// and speed lines together — rather than only the trail.
  late final Animation<double> _smokeFade = CurvedAnimation(
    parent: _c,
    curve: const Interval(0.62, 0.86, curve: Curves.easeOut),
  );

  /// The reveal only begins once the smoke has gone.
  late final Animation<double> _reveal = CurvedAnimation(
    parent: _c,
    curve: const Interval(0.84, 1, curve: Curves.easeOutBack),
  );

  /// Built once and held, rather than constructed inside the
  /// [AnimatedBuilder]: a fresh `Lottie.asset` per frame would re-read and
  /// re-decode 200 KB of JSON on every tick. The movement is applied above it
  /// by a [Positioned], which leaves this subtree — and its own
  /// `RepaintBoundary` — untouched for the length of the ride.
  late final Widget _rider = Lottie.asset(
    'assets/anim/delivery_boy.json',
    controller: _wheels,
    onLoaded: _onComposition,
    // The file is authored at 30 fps. Without this, Lottie re-rasterises at
    // display rate and draws the same frame twice on a 60 Hz panel and four
    // times on a 120 Hz one.
    frameRate: const FrameRate(30),
    fit: BoxFit.contain,
  );

  @override
  void initState() {
    super.initState();
    // The rider is gone for good once the choreography ends, so a still
    // looping file would burn a ticker on an idle screen (and would keep a
    // widget test's pumpAndSettle from ever settling).
    _c.addStatusListener(_onStage);
    _c.forward();
  }

  @override
  void dispose() {
    _c.removeStatusListener(_onStage);
    _wheels.dispose();
    _c.dispose();
    super.dispose();
  }

  void _onComposition(LottieComposition composition) {
    _wheels.duration = composition.duration;
    // Guarded because the asset can finish decoding after a short
    // choreography has already run its course — on a cold start, or on the
    // frame after a replay.
    if (_c.status != AnimationStatus.completed) {
      _wheels.repeat();
    }
  }

  void _onStage(AnimationStatus status) {
    if (status == AnimationStatus.completed) {
      _wheels.stop();
    }
  }

  void _replay() {
    _wheels.repeat();
    _c.forward(from: 0);
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
            onPressed: _replay,
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
                child: LayoutBuilder(
                  builder: (BuildContext context, BoxConstraints box) {
                    // Travel a full rider-width past both edges so it enters
                    // and leaves out of frame rather than popping into
                    // existence at the margin.
                    const double rider = 170;
                    final double travel = box.maxWidth + rider;

                    return AnimatedBuilder(
                      animation: _c,
                      builder: (BuildContext context, _) => Stack(
                        fit: StackFit.expand,
                        children: <Widget>[
                          Positioned(
                            left: -rider + _ride.value * travel,
                            // Sunk slightly below the card so the file's own
                            // road sits on the bottom edge instead of
                            // floating across the middle of it. The road is
                            // a short trim-path dash that travels with the
                            // bike, so it reads as speed lines under the
                            // wheels, not as a fixed road the card has to
                            // line up with — which is why no layer is
                            // hidden.
                            bottom: -22,
                            width: rider,
                            height: rider,
                            child: Opacity(
                              // Clamped only for safety; easeOut cannot
                              // overshoot, but Opacity asserts outside 0..1
                              // and this is a screen nobody tests by hand.
                              opacity: (1 - _smokeFade.value).clamp(0.0, 1.0),
                              child: _rider,
                            ),
                          ),
                          Center(
                            child: Opacity(
                              // Keyed so a test can assert on *this* opacity:
                              // the update card below also renders a
                              // "Version …" string, so matching on the text
                              // is ambiguous.
                              key: const ValueKey<String>('version-reveal'),
                              // Clamped: easeOutBack overshoots past 1 and
                              // Opacity asserts on anything outside 0..1.
                              opacity: _reveal.value.clamp(0.0, 1.0),
                              child: Transform.scale(
                                scale:
                                    0.92 + 0.08 * _reveal.value.clamp(0.0, 1.0),
                                child: VersionLabel(
                                  textAlign: TextAlign.center,
                                  // "Version" stays at title size; the number
                                  // itself steps up and gets the weight, so
                                  // the thing people came to this screen to
                                  // read is the thing the eye lands on.
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    color: AppColors.textSecondary,
                                  ),
                                  numberStyle:
                                      theme.textTheme.headlineMedium?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: theme.textTheme.headlineSmall?.color,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ),
          ),
          const SizedBox(height: Insets.sm),
          // Attribution is encouraged rather than required by the Lottie
          // Simple License, and it is owed either way. Kept to the smallest
          // secondary text on the screen so it credits the artist without
          // competing with the build identity it sits under.
          Text(
            'Animation by Animo Arts on LottieFiles',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              fontSize: 11,
              color: AppColors.textSecondary,
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
                // Version only — no build number, no API address. The old
                // string appended "connected to server" / the base URL, which
                // is not an employee's business and is the sort of thing that
                // travels in a screenshot.
                VersionLabel(style: theme.textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
