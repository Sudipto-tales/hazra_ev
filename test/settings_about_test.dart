import 'package:employeetracking_mobile_app/core/config/api_config.dart';
import 'package:employeetracking_mobile_app/core/config/brand.dart';
import 'package:employeetracking_mobile_app/data/company_directory.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/features/profile/app_info.dart';
import 'package:employeetracking_mobile_app/features/profile/developer_options_page.dart';
import 'package:employeetracking_mobile_app/features/profile/info_pages.dart';
import 'package:employeetracking_mobile_app/features/profile/settings_page.dart';
import 'package:employeetracking_mobile_app/features/profile/support_page.dart';
import 'package:employeetracking_mobile_app/features/profile/version_page.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/app_scope.dart';
import 'package:employeetracking_mobile_app/state/notification_center.dart';
import 'package:employeetracking_mobile_app/state/settings_controller.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// [tall] gives the test a viewport big enough that every `ListView` child is
/// actually built. Without it, anything below the fold simply is not in the
/// tree and `findsNothing` passes for the wrong reason.
Future<void> openPage(
  WidgetTester tester,
  Widget page, {
  bool tall = false,
  bool settle = true,
}) async {
  if (tall) {
    tester.view.physicalSize = const Size(1000, 4200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);
  }
  final MockEmployeeRepository employee =
      MockEmployeeRepository(latency: Duration.zero);
  final MockAdminRepository admin =
      MockAdminRepository(latency: Duration.zero);
  final MockLocationService location = MockLocationService();
  final TrackingController tracking =
      TrackingController(repository: employee, locationService: location);
  final SettingsController settings = SettingsController();
  final NotificationCenter notifications = NotificationCenter();

  addTearDown(() async {
    await tester.pumpWidget(const SizedBox.shrink());
    tracking.dispose();
    location.dispose();
    settings.dispose();
    notifications.dispose();
    await tester.pump(const Duration(seconds: 32));
  });

  await tester.pumpWidget(AppScope(
    repository: employee,
    adminRepository: admin,
    companies: CompanyDirectory(employee.companies),
    locationService: location,
    tracking: tracking,
    settings: settings,
    notifications: notifications,
    child: MaterialApp(home: page),
  ));
  // `settle: false` for a page with an entry animation: pumpAndSettle runs it
  // to completion, so a test that wants to observe a mid-flight frame has to
  // opt out of it.
  if (settle) {
    await tester.pumpAndSettle();
  } else {
    await tester.pump();
  }
}

/// Everything the About / Settings rework had to be true about.
///
/// The load-bearing group is "no backend detail is rendered": that is a
/// requirement about the *absence* of text, which nothing else in the suite
/// would catch if a future change put it back.
void main() {
  setUp(() => SharedPreferences.setMockInitialValues(<String, Object>{}));

  group('About', () {
    testWidgets('credits both companies, with the names in italic',
        (WidgetTester tester) async {
      await openPage(tester, const AboutPage());

      expect(find.textContaining('Powered by'), findsOneWidget);
      expect(find.textContaining('Developed by'), findsOneWidget);

      // The company names must be italic; the surrounding words must not be.
      // Walking the spans is the only way to assert that — a `findsOneWidget`
      // on the string would pass just as happily with no styling at all.
      final Map<String, String> credits = <String, String>{
        'Powered by ': Brand.product,
        'Developed by ': Brand.developer,
      };

      credits.forEach((String prefix, String company) {
        // Keyed on the full sentence: `Brand.product` on its own also appears
        // in the page header and the copyright line, so a bare name match
        // finds three widgets and proves nothing about this row.
        final Finder row = find.byWidgetPredicate(
          (Widget w) =>
              w is RichText && w.text.toPlainText() == '$prefix$company',
        );
        expect(row, findsOneWidget, reason: '$company should be credited once');

        TextStyle? companyStyle;
        TextStyle? prefixStyle;
        tester.widget<RichText>(row).text.visitChildren((InlineSpan child) {
          if (child is TextSpan) {
            if (child.text == company) companyStyle = child.style;
            if (child.text == prefix) prefixStyle = child.style;
          }
          return true;
        });

        expect(companyStyle?.fontStyle, FontStyle.italic,
            reason: '$company is a credited proper noun');
        expect(prefixStyle?.fontStyle, isNot(FontStyle.italic),
            reason: 'only the name is emphasised, not the whole sentence');
      });
    });

    testWidgets('copyright carries the current year, not a baked-in one',
        (WidgetTester tester) async {
      await openPage(tester, const AboutPage());

      expect(find.textContaining('${DateTime.now().year}'), findsOneWidget);
      // The literal that used to be here.
      expect(find.textContaining('Company Ltd'), findsNothing);
    });

    testWidgets('offers a Website row', (WidgetTester tester) async {
      await openPage(tester, const AboutPage());

      expect(find.text('Website'), findsOneWidget);
      expect(find.byIcon(Icons.language_rounded), findsOneWidget);
    });
  });

  group('no backend detail is rendered', () {
    /// The API host, in whatever form the build is configured with. If any of
    /// these strings reaches a widget tree, the leak is back.
    List<String> secrets() => <String>[
          ApiConfig.baseUrl,
          ApiConfig.originUrl,
          ApiConfig.describe(),
          Uri.parse(ApiConfig.baseUrl).host,
        ].where((String s) => s.trim().length > 3).toList();

    Future<void> expectNoBackendText(WidgetTester tester) async {
      for (final String secret in secrets()) {
        expect(find.textContaining(secret, skipOffstage: false), findsNothing,
            reason: 'the API address must never be rendered ($secret)');
      }
      for (final String phrase in <String>[
        'Live server',
        'Data source',
        'Backend',
        'connected to server',
      ]) {
        expect(find.textContaining(phrase, skipOffstage: false), findsNothing,
            reason: '"$phrase" was removed from the UI');
      }
    }

    testWidgets('About', (WidgetTester tester) async {
      await openPage(tester, const AboutPage());
      await expectNoBackendText(tester);
    });

    testWidgets('Settings', (WidgetTester tester) async {
      await openPage(tester, const SettingsPage());
      await expectNoBackendText(tester);
    });

    testWidgets('Report a problem', (WidgetTester tester) async {
      await openPage(tester, const ReportProblemPage());
      await expectNoBackendText(tester);
    });

    testWidgets('Developer options', (WidgetTester tester) async {
      await openPage(tester, const DeveloperOptionsPage());
      await expectNoBackendText(tester);
    });
  });

  group('Settings structure', () {
    testWidgets('the diagnostic groups are gone from the main screen',
        (WidgetTester tester) async {
      await openPage(tester, const SettingsPage(), tall: true);

      // These used to sit in a "Tracking" group between Account and
      // Notifications. They are not deleted — they moved behind Developer
      // options — so the assertion is about this screen only.
      expect(find.text('High accuracy mode'), findsNothing);
      expect(find.text('Battery saver'), findsNothing);
      expect(find.text('Simulate location state'), findsNothing);

      expect(find.text('Developer options'), findsOneWidget);
      expect(find.text('Location access'), findsOneWidget);
    });

    testWidgets('notifications-only mode shows no app chrome',
        (WidgetTester tester) async {
      await openPage(
        tester,
        const SettingsPage(initialSection: SettingsSection.notifications),
      );

      expect(find.text('Enable notifications'), findsOneWidget);
      expect(find.text('Developer options'), findsNothing);
      expect(find.byType(VersionTapCard), findsNothing);
    });

    testWidgets('developer options still carries every threshold',
        (WidgetTester tester) async {
      await openPage(tester, const DeveloperOptionsPage(), tall: true);

      for (final String group in <String>[
        'Capture',
        'Movement',
        'Stops',
        'Upload',
        'Health',
      ]) {
        expect(find.text(group), findsOneWidget, reason: '$group group');
      }

      // Read-only: the admin owns these over PUT /config.
      expect(find.byType(Slider), findsNothing);
    });
  });

  group('version card', () {
    testWidgets('four taps open the version screen',
        (WidgetTester tester) async {
      await openPage(tester, const SettingsPage(), tall: true);

      final Finder card = find.byType(VersionTapCard);
      expect(card, findsOneWidget);

      for (int i = 0; i < VersionTapCard.tapsToReveal; i++) {
        await tester.tap(card);
        await tester.pump(const Duration(milliseconds: 50));
      }
      await tester.pumpAndSettle();

      expect(find.byType(VersionPage), findsOneWidget);
    });

    testWidgets('three taps do not', (WidgetTester tester) async {
      await openPage(tester, const SettingsPage(), tall: true);

      final Finder card = find.byType(VersionTapCard);

      for (int i = 0; i < VersionTapCard.tapsToReveal - 1; i++) {
        await tester.tap(card);
        await tester.pump(const Duration(milliseconds: 50));
      }
      await tester.pumpAndSettle();

      expect(find.byType(VersionPage), findsNothing);
    });

    testWidgets('taps spread out do not accumulate',
        (WidgetTester tester) async {
      await openPage(tester, const SettingsPage(), tall: true);

      final Finder card = find.byType(VersionTapCard);

      // Three taps, then past the reset window, then one more. A stray tap
      // while scrolling must not count toward the easter egg forever.
      for (int i = 0; i < 3; i++) {
        await tester.tap(card);
        await tester.pump(const Duration(milliseconds: 50));
      }
      await tester.pump(const Duration(seconds: 3));
      await tester.tap(card);
      await tester.pumpAndSettle();

      expect(find.byType(VersionPage), findsNothing);
    });
  });

  group('version screen', () {
    testWidgets('animates, then shows the build identity',
        (WidgetTester tester) async {
      await openPage(tester, const VersionPage(), settle: false);

      // Mid-ride the reveal is still hidden behind the smoke. Targeted at the
      // Opacity that actually wraps the version text — taking the max across
      // every Opacity in the tree picked up unrelated ones sitting at 1.0.
      await tester.pump(const Duration(milliseconds: 400));
      final Finder reveal = find.byKey(const ValueKey<String>('version-reveal'));
      expect(reveal, findsOneWidget);
      expect(tester.widget<Opacity>(reveal).opacity, lessThan(1.0),
          reason: 'the build identity is still behind the smoke at 400ms');

      await tester.pumpAndSettle();
      expect(tester.widget<Opacity>(reveal).opacity, 1.0);
      expect(find.text('Version ${AppInfo.version}'), findsOneWidget);
      expect(find.text('build ${AppInfo.build}'), findsOneWidget);
    });

    testWidgets('degrades without the update gate above it',
        (WidgetTester tester) async {
      // No UpdateScope in this tree, which is the real situation on web, iOS
      // and mock builds. The card must explain itself rather than offer a
      // button that cannot work.
      await openPage(tester, const VersionPage());
      await tester.pumpAndSettle();

      expect(find.text('Check now'), findsNothing);
      expect(find.textContaining('Android app only'), findsOneWidget);
    });
  });

  group('permissions', () {
    test('every blocking health state names its own action', () {
      for (final LocationHealth h in LocationHealth.values) {
        if (h == LocationHealth.ok || h == LocationHealth.poorAccuracy) {
          expect(h.actionLabel, isNull,
              reason: '$h needs no button');
          continue;
        }
        expect(h.actionLabel, isNotNull, reason: '$h must offer a way out');
        expect(h.actionLabel, isNotEmpty);
      }
    });

    test('the three location failures ask for three different things', () {
      // The old UI said "Enable location" for all of them, which is wrong for
      // two: a denied runtime permission needs a re-request, and a
      // permanently-denied one can only be fixed in system settings.
      final Set<String?> labels = <String?>{
        LocationHealth.serviceDisabled.actionLabel,
        LocationHealth.permissionDenied.actionLabel,
        LocationHealth.permissionDeniedForever.actionLabel,
      };
      expect(labels, hasLength(3));
    });

    test('only the unrecoverable states route to system settings', () {
      expect(LocationHealth.permissionDeniedForever.needsSystemSettings, isTrue);
      expect(LocationHealth.backgroundDenied.needsSystemSettings, isTrue);
      expect(LocationHealth.serviceDisabled.needsSystemSettings, isFalse);
      expect(LocationHealth.permissionDenied.needsSystemSettings, isFalse);
      expect(LocationHealth.ok.needsSystemSettings, isFalse);
    });
  });
}
