import 'dart:convert';
import 'package:employeetracking_mobile_app/core/utils/website_links.dart';
import 'package:employeetracking_mobile_app/data/api/api_client.dart';
import 'package:employeetracking_mobile_app/data/api/api_exception.dart';
import 'package:employeetracking_mobile_app/data/api/token_store.dart';
import 'package:employeetracking_mobile_app/data/api/wire.dart';
import 'package:employeetracking_mobile_app/data/company_directory.dart';
import 'package:employeetracking_mobile_app/data/mock/account_deletion_store.dart';
import 'package:employeetracking_mobile_app/data/mock/mock_data.dart';
import 'package:employeetracking_mobile_app/data/repositories/http_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/http_employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/features/profile/account_deletion_page.dart';
import 'package:employeetracking_mobile_app/features/reports/widgets/product_detail_sheet.dart';
import 'package:employeetracking_mobile_app/features/reports/widgets/product_artwork.dart';
import 'package:employeetracking_mobile_app/features/admin/manage/account_deletion_requests_page.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/app_scope.dart';
import 'package:employeetracking_mobile_app/state/notification_center.dart';
import 'package:employeetracking_mobile_app/state/settings_controller.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

Map<String, dynamic> ticket() => {
      'id': 'ticket',
      'ticketNumber': 'DEL-ticket',
      'employeeId': 'employee',
      'employee': {'name': 'Employee', 'email': 'e@test.example'},
      'reason': '',
      'status': 'pending',
      'requestedAt': '2026-10-02T08:00:00Z',
      'deleteAfter': '2026-11-01T08:00:00Z',
      'deletedAt': null
    };

Future<void> openPage(
    WidgetTester tester, Widget page, AccountDeletionStore store) async {
  final employee =
      MockEmployeeRepository(latency: Duration.zero, deletions: store);
  final admin = MockAdminRepository(latency: Duration.zero, deletions: store);
  final location = MockLocationService();
  final tracking =
      TrackingController(repository: employee, locationService: location);
  final settings = SettingsController();
  final notifications = NotificationCenter();
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
      child: MaterialApp(home: page)));
  await tester.pumpAndSettle();
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));
  test('HTTP requests use authenticated employee route and exact confirmation',
      () async {
    final requests = <http.Request>[];
    final tokens = await TokenStore.open();
    final api = ApiClient(
        tokens: tokens,
        client: MockClient((request) async {
          requests.add(request);
          return http.Response(
              jsonEncode({
                'data': request.method == 'GET' ? null : ticket(),
                'meta': {}
              }),
              200);
        }));
    final employee = HttpEmployeeRepository(api);
    expect(await employee.accountDeletionRequest(), isNull);
    final result = await employee.submitAccountDeletion(reason: 'Personal');
    expect(result.deleteAfter.difference(result.requestedAt),
        const Duration(days: 30));
    expect(requests.last.url.path, '/api/v1/me/account-deletion');
    expect(jsonDecode(requests.last.body),
        {'confirmed': true, 'reason': 'Personal'});
    await HttpAdminRepository(api)
        .approveAccountDeletion('ticket', confirmation: 'delete account');
    expect(requests.last.url.path,
        '/api/v1/account-deletion-requests/ticket/approve');
    expect(jsonDecode(requests.last.body), {'confirmation': 'delete account'});
  });
  test('deleted account response clears tokens and notifies once', () async {
    final tokens = await TokenStore.open();
    await tokens.save(AuthSession(
        accessToken: 'access',
        refreshToken: 'refresh',
        expiresAt: DateTime.now().add(const Duration(hours: 1)),
        principal: {'type': 'employee'}));
    int notifications = 0;
    final api = ApiClient(
        tokens: tokens,
        client: MockClient((_) async => http.Response(
            jsonEncode({
              'error': {'code': 'ACCOUNT_DELETED', 'message': 'Account deleted'}
            }),
            410)))
      ..onAccountDeleted = () => notifications++;
    for (int i = 0; i < 2; i++) {
      await expectLater(api.get('/me'), throwsA(isA<ApiException>()));
    }
    expect(tokens.isSignedIn, isFalse);
    expect(notifications, 1);
  });
  testWidgets('submit shows persistent deadline card on reopening',
      (tester) async {
    final store = AccountDeletionStore();
    await openPage(tester, const AccountDeletionPage(), store);
    final submit = find.widgetWithText(FilledButton, 'Submit deletion request');
    expect(tester.widget<FilledButton>(submit).onPressed, isNull);
    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pumpAndSettle();
    expect(find.text('Account deletion requested'), findsOneWidget);
    expect(
        find.textContaining(
            'Your account will be deleted within 30 days of your request.'),
        findsOneWidget);
    expect(store.requests.length, 1);
    await tester.pumpWidget(const SizedBox.shrink());
    await openPage(tester, const AccountDeletionPage(), store);
    expect(find.text('Account deletion requested'), findsOneWidget);
    expect(find.textContaining('Deletion deadline:'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
    expect(tester.takeException(), isNull);
  });
  testWidgets('admin must type exact phrase before approval', (tester) async {
    final store = AccountDeletionStore();
    final request = store.submit(MockData.employee, 'Reason');
    await openPage(tester, AccountDeletionReviewPage(request: request), store);
    final button = find.widgetWithText(FilledButton, 'Delete account');
    await tester.scrollUntilVisible(button, 240,
        scrollable: find
            .descendant(
                of: find.byType(ListView), matching: find.byType(Scrollable))
            .first);
    expect(tester.widget<FilledButton>(button).onPressed, isNull);
    await tester.ensureVisible(find.byType(TextField));
    await tester.enterText(find.byType(TextField), 'Delete account');
    await tester.pump();
    expect(tester.widget<FilledButton>(button).onPressed, isNull);
    await tester.enterText(find.byType(TextField), 'delete account');
    await tester.pump();
    expect(tester.widget<FilledButton>(button).onPressed, isNotNull);
    await tester.ensureVisible(button);
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(store.requests.values.single.status, 'deleted');
    expect(find.text('Account deleted'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
    expect(tester.takeException(), isNull);
  });
  test('public legal routes use configured website', () {
    expect(WebsiteLinks.privacy.path, '/privacy-policy');
    expect(WebsiteLinks.terms.path, '/terms-and-conditions');
  });
  test('product website links preserve slug and selected colour', () {
    final product = Wire.product({
      'id': 'product',
      'slug': 'city-scooter',
      'colors': [
        {'id': 'blue', 'name': 'Blue', 'argb': 0xff0000ff}
      ]
    });
    expect(
        WebsiteLinks.product(product, color: product.colors.first)
            .queryParameters,
        {'slug': 'city-scooter', 'color': 'blue'});
    final noSlug = Wire.product({'id': 'product'});
    expect(WebsiteLinks.product(noSlug).queryParameters, {'id': 'product'});
    expect(ProductArtwork.resolveUrl('assets/photo.webp'),
        'http://localhost:8000/assets/photo.webp');
  });
  testWidgets('product details show one honest placeholder without photos',
      (tester) async {
    final product = Wire.product({
      'id': 'product',
      'name': 'City',
      'colors': [
        {'id': 'blue', 'name': 'Blue', 'argb': 0xff0000ff}
      ]
    });
    await tester.pumpWidget(MaterialApp(
        home: Scaffold(
            body: Builder(
                builder: (context) => TextButton(
                    onPressed: () => showProductDetailSheet(context,
                        product: product, colorName: 'Blue'),
                    child: const Text('Open product'))))));
    await tester.tap(find.text('Open product'));
    await tester.pumpAndSettle();
    expect(find.text('No product photos available'), findsOneWidget);
    expect(find.byType(ProductArtwork), findsOneWidget);
    expect(find.text('Visit on website'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
