import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import '../core/utils/formatters.dart';

/// Keeps an admin screen current without anyone pressing anything.
///
/// **Why a poll and not SSE.** `website/api` does have a `/stream` endpoint,
/// but it is an admin-*web* concern: a Dart SSE client means owning reconnect,
/// backoff and token refresh over a field network, for screens that are
/// glanced at rather than watched. Re-running the screen's own load path every
/// [autoRefreshInterval] is four requests a minute while someone is actually
/// looking, and none at all when they are not.
///
/// What it guarantees:
///
/// * **Nothing runs in the background.** The poll is armed on
///   [AppLifecycleState.resumed] and torn down on every other state, so a
///   backgrounded app makes no requests. Coming back to the foreground forces
///   one catch-up load rather than waiting out the interval.
/// * **Never two loads at once.** [runRefresh] is the single entry point for
///   the timer, the pull-to-refresh and the toolbar button alike, and it
///   returns immediately while one is already in flight. Without this a slow
///   connection turns a 15 s timer into a pile-up.
/// * **Cancelled on dispose**, observer removed with it.
/// * **Honest about staleness.** [lastRefreshedAt] is stamped only when a load
///   actually produced data, so [AutoRefreshStamp] reads "updated 42s ago" on a
///   screen whose last three attempts failed — which is precisely when an
///   admin needs to know.
///
/// Apply as `with WidgetsBindingObserver, AutoRefresh<MyPage>`.
mixin AutoRefresh<T extends StatefulWidget>
    on State<T>, WidgetsBindingObserver {
  Timer? _poll;
  Timer? _tick;
  bool _inFlight = false;
  DateTime? _lastRefreshedAt;

  final ValueNotifier<DateTime> _clock =
      ValueNotifier<DateTime>(DateTime.now());

  /// Re-load cadence. Short enough that "live team status" is not a lie, long
  /// enough that a dashboard left open is not a load generator.
  Duration get autoRefreshInterval => const Duration(seconds: 15);

  /// Lets a screen opt out of the timer without losing the manual button —
  /// the employee detail page uses it to stop polling a historical date, whose
  /// numbers cannot change.
  bool get autoRefreshEnabled => true;

  /// The screen's existing load path. Return `true` when fresh data landed and
  /// `false` when the attempt failed, so the staleness stamp stays truthful.
  /// Must not throw.
  Future<bool> onAutoRefresh();

  /// Drives only the "updated Ns ago" label, through a
  /// [ValueListenableBuilder], so the seconds tick without rebuilding the page.
  ValueListenable<DateTime> get autoRefreshClock => _clock;

  /// When data last actually arrived. Null until the first successful load.
  DateTime? get lastRefreshedAt => _lastRefreshedAt;

  bool get isAutoRefreshing => _inFlight;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _arm();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _disarm();
    _clock.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (state == AppLifecycleState.resumed) {
      _arm();
      // Whatever is on screen was true when the app went away. Catch up now
      // instead of showing it for up to another interval.
      unawaited(runRefresh());
    } else {
      _disarm();
    }
  }

  void _arm() {
    _poll ??= Timer.periodic(
      autoRefreshInterval,
      (_) => unawaited(runRefresh()),
    );
    _tick ??= Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) _clock.value = DateTime.now();
    });
  }

  void _disarm() {
    _poll?.cancel();
    _poll = null;
    _tick?.cancel();
    _tick = null;
  }

  /// The one way a load is started. [manual] bypasses [autoRefreshEnabled]:
  /// the button and pull-to-refresh always work, even on a screen that has
  /// opted out of polling.
  Future<void> runRefresh({bool manual = false}) async {
    if (!mounted || _inFlight) return;
    if (!manual && !autoRefreshEnabled) return;

    _inFlight = true;
    try {
      final bool fresh = await onAutoRefresh();
      if (fresh && mounted) _lastRefreshedAt = DateTime.now();
    } finally {
      _inFlight = false;
    }
  }
}

/// The quiet "updated 7s ago" line. Small on purpose: its job is to let an
/// admin tell a live screen from a frozen one at a glance, not to compete with
/// the data.
///
/// [lastRefreshedAt] is a callback rather than a value because the mixin
/// updates its stamp without a [State.setState] — reading it inside the
/// builder is what keeps this from showing the value the page last rebuilt at.
class AutoRefreshStamp extends StatelessWidget {
  const AutoRefreshStamp({
    super.key,
    required this.clock,
    required this.lastRefreshedAt,
    this.padding = EdgeInsets.zero,
  });

  final ValueListenable<DateTime> clock;
  final DateTime? Function() lastRefreshedAt;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    final TextStyle? style = Theme.of(context)
        .textTheme
        .bodySmall
        ?.copyWith(color: AppColors.textTertiary, fontSize: 11.5);

    return Padding(
      padding: padding,
      child: ValueListenableBuilder<DateTime>(
        valueListenable: clock,
        builder: (BuildContext context, DateTime now, _) {
          final DateTime? at = lastRefreshedAt();
          // Over a minute without data on a 15 s poll means the last few
          // attempts did not land — say so rather than counting up quietly.
          final bool stale =
              at == null || now.difference(at) > const Duration(minutes: 1);
          return Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: 6,
                height: 6,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: stale ? AppColors.warning : AppColors.success,
                ),
              ),
              const SizedBox(width: Insets.xs),
              Text(
                at == null
                    ? 'Updating…'
                    : 'Live · updated ${Fmt.freshness(at, now: now)}',
                style: style,
              ),
            ],
          );
        },
      ),
    );
  }
}
