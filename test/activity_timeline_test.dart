import 'package:employeetracking_mobile_app/core/theme/app_colors.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/features/home/widgets/activity_timeline.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// An outage is two rows: "Offline" opens it, "Back online" closes it. They
/// share one event type (`trackingIssue`, the only type a timeline rebuild
/// preserves), so the ONLY thing separating them on screen is `isAlert`.
///
/// That is a quiet trap: the obvious way to write the icon switch — match on
/// the type — renders a recovery in red with a crossed-out GPS glyph, saying
/// the opposite of its own text. These tests exist to catch that.

final DateTime _t = DateTime(2026, 11, 5, 11, 4);

ActivityEvent _offline() => ActivityEvent(
      id: 'a1',
      type: ActivityType.trackingIssue,
      time: _t,
      title: 'Offline',
      subtitle: 'Device location was switched off',
      isAlert: true,
    );

ActivityEvent _backOnline() => ActivityEvent(
      id: 'a2',
      type: ActivityType.trackingIssue,
      time: _t.add(const Duration(minutes: 14)),
      title: 'Back online',
      subtitle: 'Tracking was down for 14 minutes',
    );

Future<void> _pump(WidgetTester tester, List<ActivityEvent> events) =>
    tester.pumpWidget(MaterialApp(
      home: Scaffold(body: ActivityTimeline(events: events)),
    ));

/// The colour the rail glyph was actually painted in.
Color _iconColour(WidgetTester tester, IconData glyph) =>
    tester.widget<Icon>(find.byIcon(glyph)).color!;

void main() {
  group('an outage and the row that closes it', () {
    testWidgets('offline is red and crossed out', (WidgetTester tester) async {
      await _pump(tester, <ActivityEvent>[_offline()]);

      expect(find.text('Offline'), findsOneWidget);
      expect(find.byIcon(Icons.gps_off_rounded), findsOneWidget);
      expect(_iconColour(tester, Icons.gps_off_rounded), AppColors.danger);
    });

    testWidgets('back online is neither', (WidgetTester tester) async {
      await _pump(tester, <ActivityEvent>[_backOnline()]);

      expect(find.text('Back online'), findsOneWidget);
      expect(find.text('Tracking was down for 14 minutes'), findsOneWidget);

      expect(find.byIcon(Icons.gps_off_rounded), findsNothing,
          reason: 'a recovery must not wear the outage glyph');
      expect(_iconColour(tester, Icons.gps_fixed_rounded), AppColors.success,
          reason: 'good news is not painted in the alert colour');
    });

    testWidgets('the pair reads as a pair', (WidgetTester tester) async {
      await _pump(tester, <ActivityEvent>[_offline(), _backOnline()]);

      expect(_iconColour(tester, Icons.gps_off_rounded), AppColors.danger);
      expect(_iconColour(tester, Icons.gps_fixed_rounded), AppColors.success);
    });

    testWidgets('weak GPS has no row left to render', (WidgetTester tester) async {
      // Nothing writes one any more. If a timeline ever shows "Weak GPS
      // signal" again, it came back from somewhere it was removed from.
      await _pump(tester, <ActivityEvent>[_offline(), _backOnline()]);

      expect(find.textContaining('Weak GPS'), findsNothing);
      expect(find.textContaining('accuracy', skipOffstage: false), findsNothing);
    });
  });

  group('same-instant ordering', () {
    testWidgets('a departure renders above the trip it starts',
        (WidgetTester tester) async {
      // Leaving a company and the trip that follows share one instant:
      // travelling.time is when movement began, which is when you left. The
      // timeline must read in the order it happened — leave, then travel — not
      // in enum declaration order, which would surface travelling first.
      final DateTime instant = DateTime(2026, 11, 5, 14, 0);
      await _pump(tester, <ActivityEvent>[
        ActivityEvent(
          id: 'trip',
          type: ActivityType.travelling,
          time: instant,
          title: 'Travelling',
        ),
        ActivityEvent(
          id: 'depart',
          type: ActivityType.left,
          time: instant,
          title: 'Left Nova Retail House',
        ),
      ]);

      final double leftY = tester.getTopLeft(find.text('Left Nova Retail House')).dy;
      final double travellingY = tester.getTopLeft(find.text('Travelling')).dy;
      expect(leftY, lessThan(travellingY),
          reason: 'you cannot be travelling before you have left');
    });

    testWidgets('earlier events still render above later ones',
        (WidgetTester tester) async {
      await _pump(tester, <ActivityEvent>[
        ActivityEvent(
          id: 'late',
          type: ActivityType.reportSubmitted,
          time: _t.add(const Duration(hours: 1)),
          title: 'Report submitted',
        ),
        ActivityEvent(
          id: 'early',
          type: ActivityType.arrived,
          time: _t,
          title: 'Arrived at ABC Trading Ltd.',
        ),
      ]);

      final double earlyY =
          tester.getTopLeft(find.text('Arrived at ABC Trading Ltd.')).dy;
      final double lateY = tester.getTopLeft(find.text('Report submitted')).dy;
      expect(earlyY, lessThan(lateY));
    });
  });

  group('alerts outside the health family', () {
    testWidgets('a long stay still flags red without changing type',
        (WidgetTester tester) async {
      // The sweeper and the long-dwell path both set isAlert on ordinary row
      // types; reading isAlert before the type switch must not have broken it.
      await _pump(tester, <ActivityEvent>[
        ActivityEvent(
          id: 'a3',
          type: ActivityType.stayed,
          time: _t,
          title: 'Stayed at ABC Trading Ltd.',
          duration: const Duration(minutes: 52),
          isAlert: true,
        ),
      ]);

      expect(_iconColour(tester, Icons.gps_off_rounded), AppColors.danger);
    });

    testWidgets('an ordinary row keeps its own glyph', (WidgetTester tester) async {
      await _pump(tester, <ActivityEvent>[
        ActivityEvent(
          id: 'a4',
          type: ActivityType.arrived,
          time: _t,
          title: 'Arrived at ABC Trading Ltd.',
        ),
      ]);

      expect(_iconColour(tester, Icons.place_rounded), AppColors.primary);
    });
  });
}
