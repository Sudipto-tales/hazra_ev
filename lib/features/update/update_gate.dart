import 'dart:async';

import 'package:flutter/material.dart';

import '../../data/api/api_client.dart';
import '../../widgets/brand_splash.dart';
import 'update_controller.dart';

/// Exposes the gate's [UpdateController] to the pages below it.
///
/// The controller is owned by [UpdateGate]'s state and lives above the app
/// Navigator, so every pushed route is a descendant and can reach it. This is
/// how Settings offers a manual "check for updates" without constructing a
/// second controller (which would mean a second `start()`, a second cache read
/// and two sources of truth for `requiredUpdate`).
///
/// [maybeOf] is nullable on purpose: `app.dart` only installs the gate on a
/// live Android build, so on web, iOS, or a `--dart-define=USE_MOCKS=true`
/// build there is genuinely nothing to ask. Callers render an explanation
/// instead of a dead button.
class UpdateScope extends InheritedNotifier<UpdateController> {
  const UpdateScope({
    super.key,
    required UpdateController super.notifier,
    required super.child,
  });

  static UpdateController? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<UpdateScope>()?.notifier;
}

/// Above the app Navigator: login/session navigation cannot dismiss this gate.
class UpdateGate extends StatefulWidget {
  const UpdateGate({
    super.key,
    required this.api,
    required this.child,
    this.enforce = true,
  });

  final ApiClient api;
  final Widget child;

  /// Whether a required update actually **blocks** the app.
  ///
  /// The policy lives at the composition root (`app.dart` passes
  /// `!kDebugMode`), not in here, for two reasons: a widget that reads
  /// `kDebugMode` itself cannot be tested in both states — `flutter test`
  /// always runs in debug, so the blocking path would become unreachable from
  /// the suite that exists to cover it — and "who may be force-updated" is a
  /// release-channel decision, not a property of this widget.
  ///
  /// False only skips the overlay. The controller still runs, so
  /// [UpdateScope] stays live and the version screen's "Check now" works.
  final bool enforce;

  /// How long a cold start may be held on the splash waiting for the update
  /// check to answer, before the app opens regardless.
  ///
  /// Without a limit this screen is a 20-second wall: [UpdateController]
  /// starts with `checking == true` and only clears it in `check()`'s
  /// `finally`, and `ApiClient` allows a full 20s per request. A field
  /// employee on one bar would stare at the launch screen for all of it
  /// before the app became usable.
  ///
  /// 1.8s is chosen so that the limit is almost never the thing that ends the
  /// splash. `GET /app-releases/update` is a small authenticated JSON read
  /// that returns in a few hundred milliseconds on any working connection, so
  /// on a healthy start the splash simply covers work that was happening
  /// anyway and lifts early. The budget still leaves room for a slow-but-alive
  /// 2G/3G round-trip, which matters: an answer that arrives inside the box is
  /// an update enforced *before* the app opens, which is what the gate is for.
  /// Past roughly two seconds a launch screen stops reading as "starting" and
  /// starts reading as "stuck", and there is nothing to gain by waiting — the
  /// controller's own policy is already that "a first-time network failure
  /// permits field work". Giving up and opening the app is that policy, not a
  /// change to it: the check keeps running, and if it comes back saying an
  /// update is required the gate closes then.
  static const Duration splashTimeBox = Duration(milliseconds: 1800);

  @override
  State<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends State<UpdateGate> with WidgetsBindingObserver {
  late final UpdateController updates;
  Timer? _sessionWatcher;
  Timer? _splashTimer;
  late bool _signedIn;

  /// Set once, by [UpdateGate.splashTimeBox] elapsing. Never reset: the splash
  /// belongs to the cold start only. `check()` does not set `checking` back to
  /// true, so resume-time and manual checks are already silent and must stay
  /// that way.
  bool _splashExpired = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    updates = UpdateController(widget.api);
    _signedIn = updates.signedIn;
    unawaited(updates.start());
    _splashTimer = Timer(UpdateGate.splashTimeBox, () {
      if (mounted) setState(() => _splashExpired = true);
    });
    // Login persists its token without rebuilding MaterialApp. Observe only
    // session changes here; network checks run on startup/login/resume.
    _sessionWatcher = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_signedIn != updates.signedIn) {
        _signedIn = updates.signedIn;
        unawaited(updates.check());
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(updates.resumed());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sessionWatcher?.cancel();
    _splashTimer?.cancel();
    updates.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: updates,
        builder: (context, _) {
          // Both the splash and the update screen hang off this. The splash
          // exists only because the gate is making the user wait; where the
          // gate would not block anyway — a signed-out device on the sign-in
          // screen, or a locally built `enforce: false` app — there is no
          // wait to cover, and holding the launch screen would be a delay
          // this widget invented rather than one it is explaining.
          final gating = widget.enforce && updates.signedIn;

          // A requirement already in hand — from the SharedPreferences cache
          // read `start()` does before it touches the network, or from a
          // check that has since answered. Either way there is nothing to
          // wait for, so this wins over the splash.
          final mustUpdate = gating && updates.requiredUpdate;

          // Nothing known yet and the cold-start check is still out. Show the
          // brand instead of the app, but only until the time box expires —
          // see [UpdateGate.splashTimeBox] for why the limit is the point.
          final splash =
              gating && updates.checking && !mustUpdate && !_splashExpired;

          final blocked = mustUpdate || splash;
          return Stack(
            fit: StackFit.expand,
            children: <Widget>[
              Offstage(
                offstage: blocked,
                // Inside the Offstage, so the scope wraps the app's own
                // subtree (and therefore every route pushed from it) rather
                // than only the blocking overlay.
                child: UpdateScope(notifier: updates, child: widget.child),
              ),
              // Deliberately a sibling of the Offstage, not a replacement for
              // it: the app's subtree keeps building behind the splash, so
              // when the splash lifts the first screen is already laid out
              // and there is no second blank frame.
              if (splash) const BrandSplash(),
              if (mustUpdate)
                Scaffold(
                  body: SafeArea(
                    child: Center(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.all(28),
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 440),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              const Icon(Icons.system_update, size: 64),
                              const SizedBox(height: 24),
                              // Unconditional: this branch only renders when
                              // `release != null`, so there is no
                              // "Checking for updates…" state left to show.
                              // A cached requirement lands straight here with
                              // the refresh still in flight behind it.
                              Text('Update required',
                                  style: Theme.of(context)
                                      .textTheme
                                      .headlineSmall),
                              const SizedBox(height: 16),
                              Text(
                                  'Hazra EV ${updates.release!['versionName']} is available. Install this update to continue.',
                                  textAlign: TextAlign.center),
                              const SizedBox(height: 16),
                              if ((updates.release!['releaseNotes']
                                          as String? ??
                                      '')
                                  .isNotEmpty)
                                Text(
                                    updates.release!['releaseNotes'] as String),
                              const SizedBox(height: 24),
                              if (updates.busy) ...<Widget>[
                                LinearProgressIndicator(
                                    value: updates.ready
                                        ? null
                                        : updates.progress / 100),
                                const SizedBox(height: 12),
                                Text(updates.ready
                                    ? 'Opening installer…'
                                    : 'Downloading… ${updates.progress}%'),
                              ] else ...<Widget>[
                                Text(
                                    updates.waitingForPermission
                                        ? 'Allow installs from Hazra EV in Android settings. The install prompt will open when you return.'
                                        : 'Tap Install in the Android popup. If you cancelled, you can open it again below.',
                                    textAlign: TextAlign.center),
                                const SizedBox(height: 16),
                                FilledButton(
                                  onPressed: () => unawaited(updates.ready
                                      ? updates.install()
                                      : updates.check()),
                                  child: Text(updates.ready
                                      ? 'Install update'
                                      : 'Retry update'),
                                ),
                              ],
                              if (updates.error != null) ...<Widget>[
                                const SizedBox(height: 16),
                                Text(updates.error!,
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .error)),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          );
        },
      );
}
