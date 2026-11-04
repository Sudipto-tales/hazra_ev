import 'dart:async';

import 'package:employeetracking_mobile_app/widgets/auto_refresh.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The admin screens' polling contract.
///
/// These screens used to update only on a button press, which made "live team
/// status" a label rather than a fact. The mixin that fixes that has four
/// obligations, and every one of them is a way it could quietly become a
/// battery or load problem instead: poll on a timer, stop dead when the app is
/// not in front of anyone, never overlap two loads, and leave the manual path
/// working.

class _Host extends StatefulWidget {
  const _Host({required this.load, this.enabled = true});

  final Future<bool> Function() load;
  final bool enabled;

  @override
  State<_Host> createState() => _HostState();
}

class _HostState extends State<_Host>
    with WidgetsBindingObserver, AutoRefresh<_Host> {
  @override
  bool get autoRefreshEnabled => widget.enabled;

  @override
  Future<bool> onAutoRefresh() => widget.load();

  @override
  Widget build(BuildContext context) => MaterialApp(
        home: Scaffold(
          body: Column(
            children: <Widget>[
              AutoRefreshStamp(
                clock: autoRefreshClock,
                lastRefreshedAt: () => lastRefreshedAt,
              ),
              TextButton(
                onPressed: () => unawaited(runRefresh(manual: true)),
                child: const Text('Refresh'),
              ),
            ],
          ),
        ),
      );
}

void main() {
  testWidgets('polls on the interval and stops on dispose', (WidgetTester t) async {
    int loads = 0;
    Future<bool> load() async {
      loads++;
      return true;
    }

    await t.pumpWidget(_Host(load: load));
    await t.pump();
    final int afterMount = loads;

    await t.pump(const Duration(seconds: 15));
    await t.pump();
    expect(loads, afterMount + 1);

    await t.pump(const Duration(seconds: 15));
    await t.pump();
    expect(loads, afterMount + 2);

    // Tear the screen down; the timer must go with it.
    await t.pumpWidget(const SizedBox.shrink());
    final int atDispose = loads;
    await t.pump(const Duration(seconds: 60));
    expect(loads, atDispose, reason: 'a disposed screen keeps polling');
  });

  testWidgets('pauses while the app is not resumed', (WidgetTester t) async {
    int loads = 0;
    Future<bool> load() async {
      loads++;
      return true;
    }

    await t.pumpWidget(_Host(load: load));
    await t.pump();

    t.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await t.pump();
    final int backgrounded = loads;

    await t.pump(const Duration(seconds: 60));
    await t.pump();
    expect(loads, backgrounded, reason: 'a backgrounded app must make no calls');

    // Returning forces one catch-up rather than waiting out the interval.
    t.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await t.pump();
    expect(loads, backgrounded + 1);

    await t.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('never runs two loads at once', (WidgetTester t) async {
    int started = 0;
    final Completer<bool> gate = Completer<bool>();
    Future<bool> load() {
      started++;
      return gate.future;
    }

    await t.pumpWidget(_Host(load: load));
    await t.pump(const Duration(seconds: 15));
    await t.pump();
    expect(started, 1);

    // More triggers — timer and button alike — while the first is in flight.
    await t.pump(const Duration(seconds: 15));
    await t.tap(find.text('Refresh'));
    await t.pump(const Duration(seconds: 15));
    expect(started, 1, reason: 'a slow load must not pile up behind itself');

    gate.complete(true);
    await t.pump();
    await t.pump(const Duration(seconds: 15));
    await t.pump();
    expect(started, 2);

    await t.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('the manual path works even with polling switched off',
      (WidgetTester t) async {
    int loads = 0;
    Future<bool> load() async {
      loads++;
      return true;
    }

    await t.pumpWidget(_Host(load: load, enabled: false));
    await t.pump();
    expect(loads, 0, reason: 'mount does not auto-load a disabled screen');

    await t.pump(const Duration(seconds: 60));
    expect(loads, 0);

    await t.tap(find.text('Refresh'));
    await t.pump();
    expect(loads, 1);

    await t.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('the stamp only counts from a load that actually landed',
      (WidgetTester t) async {
    bool ok = false;
    Future<bool> load() async => ok;

    await t.pumpWidget(_Host(load: load));
    await t.pump();
    await t.pump(const Duration(seconds: 1));
    expect(find.text('Updating…'), findsOneWidget,
        reason: 'a failed load must not read as fresh data');

    ok = true;
    await t.tap(find.text('Refresh'));
    await t.pump();
    await t.pump(const Duration(seconds: 1));
    expect(find.textContaining('updated'), findsOneWidget);

    await t.pumpWidget(const SizedBox.shrink());
  });
}
