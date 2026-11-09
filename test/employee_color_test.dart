import 'package:employeetracking_mobile_app/core/theme/app_colors.dart';
import 'package:employeetracking_mobile_app/features/admin/map/route_painter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Identity colour is only useful if it is *stable*: the same employee has to
/// get the same hue on every screen, every device and every launch, or the
/// legend lies. These tests pin that down, plus the two things that make the
/// palette worth having — that it spreads ids across the whole list and that
/// the route screen actually threads the colour into its stroke and glow.
void main() {
  group('employeeColor', () {
    test('is deterministic for the same id', () {
      for (final String id in <String>['e1', 'EMP-042', 'abc123', '', 'x']) {
        expect(
          AppColors.employeeColor(id),
          AppColors.employeeColor(id),
          reason: 'same id must always map to the same colour',
        );
      }
    });

    test('every resolved colour is in the palette', () {
      for (int i = 0; i < 500; i++) {
        final Color c = AppColors.employeeColor('employee-$i');
        expect(AppColors.trailPalette, contains(c));
      }
    });

    test('index is in range for any id, including empty', () {
      final int n = AppColors.trailPalette.length;
      for (final String id in <String>['', 'a', 'a very long id string 12345']) {
        final int idx = AppColors.employeeColorIndex(id);
        expect(idx, inInclusiveRange(0, n - 1));
      }
    });

    test('different ids spread across the whole palette', () {
      final Set<int> seen = <int>{};
      for (int i = 0; i < 300; i++) {
        seen.add(AppColors.employeeColorIndex('emp-$i'));
      }
      // A hash worth using fills every slot given a few hundred ids; a broken
      // one collapses onto one or two. Assert the whole palette is reachable.
      expect(seen.length, AppColors.trailPalette.length);
    });

    test('does not lean on String.hashCode (hard-coded golden indices)', () {
      // Golden values computed from the FNV-1a implementation. If someone swaps
      // the hash for String.hashCode — which the VM may seed per run — these
      // break, which is the whole point: the mapping must not be allowed to
      // drift between launches.
      expect(AppColors.employeeColorIndex(''), 0x811c9dc5 % 10);
      expect(
        AppColors.employeeColor('e1'),
        AppColors.trailPalette[AppColors.employeeColorIndex('e1')],
      );
    });
  });

  group('RouteMapColors threads the employee hue', () {
    testWidgets('stroke, glow and rough follow routeColor; markers stay '
        'semantic', (WidgetTester tester) async {
      late BuildContext ctx;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (BuildContext context) {
              ctx = context;
              return const SizedBox();
            },
          ),
        ),
      );

      const Color hue = Color(0xFF7048E8);
      final RouteMapColors tinted =
          RouteMapColors.of(ctx, routeColor: hue);

      expect(tinted.stroke, hue);
      // Glow and rough are the same hue at a lower alpha, so the RGB channels
      // match even though the alpha does not.
      expect(<double>[tinted.glow.r, tinted.glow.g, tinted.glow.b],
          <double>[hue.r, hue.g, hue.b]);
      expect(<double>[tinted.rough.r, tinted.rough.g, tinted.rough.b],
          <double>[hue.r, hue.g, hue.b]);

      // Identity must not bleed into the semantic marker colours.
      expect(tinted.stop, AppColors.warning);
      expect(tinted.longStop, AppColors.danger);
      expect(tinted.start, AppColors.success);
      expect(tinted.visit, AppColors.primary);

      // No colour given falls back to the brand primary, so an un-threaded
      // caller still renders.
      final RouteMapColors plain = RouteMapColors.of(ctx);
      expect(plain.stroke, AppColors.primary);
    });
  });
}
