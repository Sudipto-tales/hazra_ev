import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:employeetracking_mobile_app/features/update/update_gate.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:employeetracking_mobile_app/data/api/api_client.dart';
import 'package:employeetracking_mobile_app/data/api/token_store.dart';
import 'package:employeetracking_mobile_app/features/profile/app_info.dart';
import 'package:employeetracking_mobile_app/features/update/update_controller.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('hazra_ev/updater');
  late Map<String, dynamic> release;
  late List<MethodCall> calls;
  late ApiClient api;
  late UpdateController updates;
  late TokenStore tokens;
  bool offline = false,
      withdrawn = false,
      permission = true,
      failDownload = false;
  int requests = 0;
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    AppInfo.build = '3';
    offline = false;
    withdrawn = false;
    permission = true;
    failDownload = false;
    requests = 0;
    calls = [];
    release = {
      'id': 'release-4',
      'versionName': '0.1.3',
      'versionCode': 4,
      'fileSize': 100,
      'checksumSha256': 'a' * 64,
      'available': true,
      'minAndroidSdk': 24,
      'androidRequirement': 'Android 7.0+',
    };
    tokens = await TokenStore.open();
    await tokens.save(AuthSession(
        accessToken: 'access',
        refreshToken: 'refresh',
        expiresAt: DateTime.now().add(const Duration(hours: 1)),
        principal: const {'type': 'employee'}));
    api = ApiClient(
        tokens: tokens,
        client: MockClient((request) async {
          requests++;
          expect(request.headers['Authorization'], 'Bearer access');
          if (offline) throw http.ClientException('offline');
          if (withdrawn) {
            return http.Response(
                jsonEncode({
                  'error': {
                    'code': 'NO_PUBLISHED_RELEASE',
                    'message': 'No release'
                  }
                }),
                404);
          }
          return http.Response(jsonEncode({'data': release}), 200);
        }));
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      calls.add(call);
      switch (call.method) {
        case 'sdk':
          return 30;
        case 'canInstall':
          return permission;
        case 'download':
          if (failDownload) {
            throw PlatformException(
                code: 'DOWNLOAD_FAILED', message: 'Checksum mismatch');
          }
          return null;
        case 'install':
          return permission;
      }
      throw StateError('Unexpected ${call.method}');
    });
    updates = UpdateController(api);
  });
  tearDown(() {
    updates.dispose();
    api.dispose();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });
  test('newer release downloads with auth and opens installer automatically',
      () async {
    await updates.start();
    expect(updates.requiredUpdate, isTrue);
    expect(updates.ready, isTrue);
    expect(calls.map((c) => c.method), ['sdk', 'download', 'install']);
    final args = calls[1].arguments as Map;
    expect(args['token'], 'access');
    expect(args['checksum'], release['checksumSha256']);
    expect(args['version'], 4);
    expect(args['url'], endsWith('/app-releases/release-4/update-apk'));
    expect(
        (await SharedPreferences.getInstance())
            .getString(UpdateController.cacheKey),
        isNotNull);
  });
  test('current and newer installed builds are never downgraded', () async {
    release['versionCode'] = 3;
    await updates.start();
    expect(updates.requiredUpdate, isFalse);
    AppInfo.build = '5';
    await updates.check();
    expect(updates.requiredUpdate, isFalse);
    expect(calls.map((c) => c.method), ['sdk']);
  });
  test('cancelling installer keeps gate; retry installs without downloading',
      () async {
    await updates.start();
    await updates.resumed();
    expect(updates.requiredUpdate, isTrue);
    expect(calls.where((c) => c.method == 'install').length, 1);
    await updates.install();
    expect(calls.where((c) => c.method == 'install').length, 2);
    expect(calls.where((c) => c.method == 'download').length, 1);
  });
  test('permission grant resumes installation; denial does not loop Settings',
      () async {
    permission = false;
    await updates.start();
    expect(updates.waitingForPermission, isTrue);
    await updates.resumed();
    expect(updates.waitingForPermission, isFalse);
    expect(updates.error, contains('Allow installs'));
    expect(calls.where((c) => c.method == 'install').length, 1);
    await updates.install();
    permission = true;
    await updates.resumed();
    expect(updates.waitingForPermission, isFalse);
    expect(calls.where((c) => c.method == 'install').length, 3);
  });
  test('known update survives restart and network outage', () async {
    await (await SharedPreferences.getInstance())
        .setString(UpdateController.cacheKey, jsonEncode(release));
    offline = true;
    await updates.start();
    expect(updates.requiredUpdate, isTrue);
    expect(updates.error, isNotNull);
    expect(updates.ready, isFalse);
  });
  test('withdrawal clears a previously required update', () async {
    await updates.start();
    withdrawn = true;
    await updates.check();
    expect(updates.requiredUpdate, isFalse);
    expect(
        (await SharedPreferences.getInstance())
            .getString(UpdateController.cacheKey),
        isNull);
  });
  test('incompatible or unavailable release blocks without installing',
      () async {
    release['minAndroidSdk'] = 35;
    await updates.start();
    expect(updates.requiredUpdate, isTrue);
    expect(updates.error, contains('requires'));
    release['available'] = false;
    await updates.check();
    expect(updates.error, contains('unavailable'));
    expect(calls.map((c) => c.method), ['sdk']);
  });
  test('verification failure never opens installer and can be retried',
      () async {
    failDownload = true;
    await updates.start();
    expect(updates.requiredUpdate, isTrue);
    expect(updates.ready, isFalse);
    expect(calls.where((c) => c.method == 'install'), isEmpty);
    failDownload = false;
    await updates.check();
    expect(updates.ready, isTrue);
    expect(calls.where((c) => c.method == 'install').length, 1);
  });
  test('signed-out users can log in before protected download', () async {
    await tokens.clear();
    await updates.start();
    expect(requests, 0);
    expect(updates.checking, isFalse);
    expect(updates.requiredUpdate, isFalse);
  });
  testWidgets('update screen stays above navigation and install retry works',
      (tester) async {
    final navigator = GlobalKey<NavigatorState>();
    await tester.pumpWidget(MaterialApp(
      navigatorKey: navigator,
      home: const Scaffold(body: Text('Employee home')),
      builder: (context, child) => UpdateGate(api: api, child: child!),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Update required'), findsOneWidget);
    expect(find.text('Employee home'), findsNothing);
    navigator.currentState!.push(MaterialPageRoute<void>(
        builder: (_) => const Scaffold(body: Text('Another route'))));
    await tester.pumpAndSettle();
    expect(find.text('Update required'), findsOneWidget);
    expect(find.text('Another route'), findsNothing);
    await tester.tap(find.text('Install update'));
    await tester.pumpAndSettle();
    expect(calls.where((call) => call.method == 'install').length, 2);
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('enforce: false lets the app through but keeps the controller',
      (tester) async {
    // What a locally built app does. The same payload that blocks above must
    // not block here — a debug build is signed with the debug key and carries
    // a `.local` applicationId, so the production APK this gate would install
    // cannot replace it, and enforcing would dead-end the app behind an
    // install that cannot succeed.
    //
    // `app.dart` passes `enforce: !kDebugMode`. The flag is a constructor
    // argument rather than a `kDebugMode` read inside the widget precisely so
    // both branches stay reachable from here: `flutter test` always runs in
    // debug, so a widget that checked `kDebugMode` itself would make the
    // blocking path above untestable.
    await tester.pumpWidget(MaterialApp(
      home: const Scaffold(body: Text('Employee home')),
      builder: (context, child) =>
          UpdateGate(api: api, enforce: false, child: child!),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Update required'), findsNothing);
    expect(find.text('Employee home'), findsOneWidget);

    // Only the overlay is skipped. The controller still ran its check, so
    // UpdateScope is live and the version screen's "Check now" still works.
    expect(requests, greaterThan(0),
        reason: 'the update check must still run, it just does not block');

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('UpdateScope publishes the controller to the pages below',
      (tester) async {
    UpdateController? seen;

    await tester.pumpWidget(MaterialApp(
      home: const Scaffold(body: Text('home')),
      builder: (context, child) => UpdateGate(
        api: api,
        enforce: false,
        child: Builder(
          builder: (BuildContext inner) {
            seen = UpdateScope.maybeOf(inner);
            return child!;
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();

    // The version screen's "Check now" reaches the gate's controller through
    // this, instead of constructing a second one with its own cache read and
    // its own idea of `requiredUpdate`.
    expect(seen, isNotNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('maybeOf is null with no gate above, so callers can degrade',
      (tester) async {
    UpdateController? seen;
    bool built = false;

    // Web, iOS and `--dart-define=USE_MOCKS=true` all run without the gate.
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (BuildContext context) {
          seen = UpdateScope.maybeOf(context);
          built = true;
          return const Scaffold(body: Text('home'));
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(built, isTrue, reason: 'absence must not throw');
    expect(seen, isNull);
  });
}
