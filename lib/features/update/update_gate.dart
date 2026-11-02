import 'dart:async';

import 'package:flutter/material.dart';

import '../../data/api/api_client.dart';
import 'update_controller.dart';

/// Above the app Navigator: login/session navigation cannot dismiss this gate.
class UpdateGate extends StatefulWidget {
  const UpdateGate({super.key, required this.api, required this.child});
  final ApiClient api;
  final Widget child;

  @override
  State<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends State<UpdateGate> with WidgetsBindingObserver {
  late final UpdateController updates;
  Timer? _sessionWatcher;
  late bool _signedIn;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    updates = UpdateController(widget.api);
    _signedIn = updates.signedIn;
    unawaited(updates.start());
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
    updates.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: updates,
        builder: (context, _) {
          final blocked =
              updates.signedIn && (updates.checking || updates.requiredUpdate);
          return Stack(
            fit: StackFit.expand,
            children: <Widget>[
              Offstage(offstage: blocked, child: widget.child),
              if (blocked)
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
                              Text(
                                  updates.checking
                                      ? 'Checking for updates…'
                                      : 'Update required',
                                  style: Theme.of(context)
                                      .textTheme
                                      .headlineSmall),
                              const SizedBox(height: 16),
                              if (updates.checking)
                                const CircularProgressIndicator()
                              else ...<Widget>[
                                Text(
                                    'Hazra EV ${updates.release!['versionName']} is available. Install this update to continue.',
                                    textAlign: TextAlign.center),
                                const SizedBox(height: 16),
                                if ((updates.release!['releaseNotes']
                                            as String? ??
                                        '')
                                    .isNotEmpty)
                                  Text(updates.release!['releaseNotes']
                                      as String),
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
