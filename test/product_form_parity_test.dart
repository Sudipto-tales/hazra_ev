import 'dart:convert';
import 'dart:typed_data';
import 'package:image_picker/image_picker.dart';

import 'package:employeetracking_mobile_app/data/api/api_client.dart';
import 'package:employeetracking_mobile_app/data/api/api_exception.dart';
import 'package:employeetracking_mobile_app/data/api/token_store.dart';
import 'package:employeetracking_mobile_app/data/api/wire.dart';
import 'package:employeetracking_mobile_app/data/company_directory.dart';
import 'package:employeetracking_mobile_app/data/mock/product_store.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/models/product_page_content.dart';
import 'package:employeetracking_mobile_app/data/repositories/http_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/features/admin/products/product_form_page.dart';
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

Map<String, dynamic> webProduct() => {
      'id': 'web-product',
      'category': 'scooty',
      'brand': 'Hazra EV',
      'name': 'City',
      'modelCode': 'c',
      'rating': 0.17,
      'warrantyYears': 0,
      'warrantyNote': '',
      'rangeKm': 0,
      'topSpeedKmph': 0,
      'chargingTime': '',
      'batteryCapacity': '',
      'motorPower': '',
      'loadCapacityKg': 0,
      'active': false,
      'slug': 'city-scooter',
      'isFeatured': true,
      'featuredOrder': 4,
      'heroImage': '',
      'defaultColorId': 'blue',
      'highlights': ['Comfortable seat'],
      'pageContent': {
        'hero_title': 'Custom\n{name}',
        'show_range': false,
        'related_ids': ['related-product'],
        'cinematic_link': '#features'
      },
      'colors': [
        {
          'id': 'red',
          'name': 'Red',
          'argb': 0xfff0532b,
          'position': 0,
          'inStock': false,
          'imageUrls': <String>[]
        },
        {
          'id': 'blue',
          'name': 'Blue',
          'argb': 0xff12a5e0,
          'position': 1,
          'inStock': false,
          'imageUrls': <String>[]
        },
      ],
      'featureCards': [
        {
          'id': 'card-1',
          'title': 'Lighting',
          'description': 'LED lights',
          'image': '',
          'alt': 'Headlamp',
          'color_id': 'blue',
          'visible': false,
          'legacy_crop': true
        },
      ],
    };

class RecordingAdmin extends MockAdminRepository {
  RecordingAdmin({this.failFirst = false}) : super(latency: Duration.zero);
  final bool failFirst;
  final List<ProductDraft> attempts = [];
  @override
  Future<List<Product>> products(
          {ProductCategory? category, String? query}) async =>
      [];
  @override
  Future<Product> saveProduct(ProductDraft draft) async {
    attempts.add(draft);
    if (failFirst && attempts.length == 1) {
      throw const ApiException(
          code: 'PRODUCT_IDENTIFIER_TAKEN',
          message: 'That model code or slug already exists');
    }
    return Wire.product(webProduct());
  }
}

Future<void> openForm(WidgetTester tester, RecordingAdmin admin,
    {Product? product, bool create = false}) async {
  final employee = MockEmployeeRepository(latency: Duration.zero);
  final location =
      MockLocationService(emitInterval: const Duration(seconds: 30));
  final tracking =
      TrackingController(repository: employee, locationService: location);
  final notifications = NotificationCenter();
  final settings = SettingsController();
  addTearDown(() async {
    await tester.pumpWidget(const SizedBox.shrink());
    tracking.dispose();
    location.dispose();
    notifications.dispose();
    settings.dispose();
    await tester.pump(const Duration(seconds: 32));
  });
  await tester.pumpWidget(AppScope(
    repository: employee,
    adminRepository: admin,
    companies: CompanyDirectory(() => employee.companies()),
    locationService: location,
    tracking: tracking,
    settings: settings,
    notifications: notifications,
    child: MaterialApp(
        home: ProductFormPage(
            product: create ? null : product ?? Wire.product(webProduct()))),
  ));
  await tester.pumpAndSettle();
}

Future<void> tapVisible(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
  await tester.pumpAndSettle();
}

void main() {
  test('product photos upload once to the registered endpoint', () async {
    SharedPreferences.setMockInitialValues({});
    final tokens = await TokenStore.open();
    final client = MockClient((request) async {
      expect(request.url.path, '/api/v1/upload');
      expect(request.method, 'POST');
      expect(
          request.headers['content-type'], startsWith('multipart/form-data'));
      expect(request.body, contains('name="file"; filename="photo.png"'));
      return http.Response(
          jsonEncode({
            'data': {'url': 'assets/uploads/images/photo.png'},
            'meta': {}
          }),
          200);
    });
    final api = ApiClient(tokens: tokens, client: client);
    addTearDown(api.dispose);
    expect(
        await api.uploadProductImage(
            XFile.fromData(Uint8List.fromList([1, 2, 3]), name: 'photo.png', path: 'photo.png')),
        'assets/uploads/images/photo.png');
  });

  test('HTTP edit preserves website content and stable colour references',
      () async {
    TestWidgetsFlutterBinding.ensureInitialized();
    SharedPreferences.setMockInitialValues({});
    Map<String, dynamic>? written;
    final client = MockClient((request) async {
      if (request.method == 'PATCH') {
        written = jsonDecode(request.body) as Map<String, dynamic>;
      }
      return http.Response(
          jsonEncode({'data': webProduct(), 'meta': {}, 'error': null}), 200);
    });
    addTearDown(client.close);
    final repository = HttpAdminRepository(
        ApiClient(tokens: await TokenStore.open(), client: client));
    final original = await repository.productById('web-product');
    await repository.saveProduct(ProductDraft.from(original));
    expect(written!['slug'], 'city-scooter');
    expect(written!['active'], false);
    expect(written!['isFeatured'], true);
    expect(written!['featuredOrder'], 4);
    expect(written!['defaultColorId'], 'blue');
    expect(written!['pageContent'], original.pageContent);
    expect(written!['featureCards'], webProduct()['featureCards']);
    expect((written!['colors'] as List).map((c) => c['id']), ['red', 'blue']);
    expect((written!['colors'] as List).map((c) => c['position']), [0, 1]);
    expect(written!['rating'], 0.17);
  });

  test('colour and photo ordering writes array positions without changing IDs',
      () {
    final payload = webProduct();
    final colors = payload['colors'] as List;
    colors[0]['imageUrls'] = ['assets/rear.webp', 'assets/front.webp'];
    payload['colors'] = colors.reversed.toList();
    final body =
        Wire.productDraftToJson(ProductDraft.from(Wire.product(payload)));
    expect((body['colors'] as List).map((c) => c['id']), ['blue', 'red']);
    expect((body['colors'] as List).map((c) => c['position']), [0, 1]);
    expect((body['colors'] as List)[1]['imageUrls'],
        ['assets/rear.webp', 'assets/front.webp']);
    expect(body['defaultColorId'], 'blue');
  });

  test('mock editing preserves website settings and hidden publication', () {
    final original = Wire.product(webProduct());
    final store = ProductStore(seed: [original]);
    addTearDown(store.dispose);
    expect(store.active, isEmpty);
    final edited = store.upsert(ProductDraft.from(original));
    expect(edited.pageContent, original.pageContent);
    expect(edited.featureCards.first.colorId, 'blue');
    expect(store.isActive(edited.id), false);
    store.setActive(edited.id, true);
    expect(store.byId(edited.id)!.active, true);
    expect(store.byId(edited.id)!.slug, original.slug);
    expect(store.byId(edited.id)!.defaultColorId, 'blue');
  });

  testWidgets(
      'save failure leaves the form usable and accepts web-valid values',
      (tester) async {
    final admin = RecordingAdmin(failFirst: true);
    await openForm(tester, admin);
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    expect(find.text('That model code or slug already exists'), findsOneWidget);
    expect(admin.attempts, hasLength(1));
    expect(admin.attempts.first.active, false);
    expect(admin.attempts.first.rating, 0.17);
    expect(admin.attempts.first.warrantyYears, 0);
    expect(admin.attempts.first.colors.every((c) => !c.inStock), true);
    expect(admin.attempts.first.pageContent['hero_title'], 'Custom\n{name}');
    expect(
        admin.attempts.first.pageContent['related_ids'], ['related-product']);
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    expect(admin.attempts, hasLength(2));
  });

  testWidgets(
      'removing default colour repairs feature references before saving',
      (tester) async {
    final admin = RecordingAdmin();
    await openForm(tester, admin);
    await tapVisible(tester, find.byTooltip('Remove colour').last);
    expect(
        find.text('1 feature card(s) will become shared across all colours.'),
        findsOneWidget);
    await tester.tap(find.text('Remove'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    expect(admin.attempts, hasLength(1));
    expect(admin.attempts.single.defaultColorId, 'red');
    expect(admin.attempts.single.colors.map((c) => c.id), ['red']);
    expect(admin.attempts.single.featureCards.single.colorId, '');
    expect(admin.attempts.single.featureCards.single.legacyCrop, true);
  });

  testWidgets('custom colour editing retains its ID and existing relationships',
      (tester) async {
    final admin = RecordingAdmin();
    await openForm(tester, admin);
    await tapVisible(tester, find.text('Blue').last);
    final hex = find.widgetWithText(TextFormField, 'Custom colour (HEX)');
    await tester.enterText(hex, '#123456');
    await tester.tap(find.text('Save colour'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    expect(admin.attempts.single.colors.last.id, 'blue');
    expect(admin.attempts.single.colors.last.argb, 0xff123456);
    expect(admin.attempts.single.featureCards.single.colorId, 'blue');
  });

  testWidgets('collapsed page fields still validate before any API write',
      (tester) async {
    final admin = RecordingAdmin();
    final data = webProduct();
    (data['pageContent'] as Map)['cinematic_link'] = 'javascript:alert(1)';
    await openForm(tester, admin, product: Wire.product(data));
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    expect(admin.attempts, isEmpty);
    expect(
        find.text(
            'Check the highlighted fields, including the page content sections.'),
        findsOneWidget);
  });

  testWidgets('add form saves website defaults with optional specifications',
      (tester) async {
    final admin = RecordingAdmin();
    await openForm(tester, admin, create: true);
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Product name'), 'New scooter');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Model code'), 'a');
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    final draft = admin.attempts.single;
    expect(draft.isCreate, true);
    expect(draft.brand, 'Hazra EV');
    expect(draft.active, true);
    expect(draft.rating, 0);
    expect(draft.loadCapacityKg, 0);
    expect(draft.warrantyYears, 3);
    expect(draft.pageContent, productPageDefaults);
    expect(draft.defaultColorId, draft.colors.single.id);
    expect(draft.colors.single.id, isNotEmpty);
    expect(draft.featureCards, isEmpty);
  });

  testWidgets('feature editor keeps card identity and colour association',
      (tester) async {
    final admin = RecordingAdmin();
    await openForm(tester, admin);
    await tapVisible(tester, find.text('Lighting'));
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Title'), 'LED lighting');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Image alt text'), 'LED headlamp');
    await tester.tap(find.text('Save feature card'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    final card = admin.attempts.single.featureCards.single;
    expect(card.id, 'card-1');
    expect(card.title, 'LED lighting');
    expect(card.alt, 'LED headlamp');
    expect(card.colorId, 'blue');
    expect(card.visible, false);
    expect(card.legacyCrop, true);
  });

  testWidgets('gallery reorder changes the primary photo on a narrow phone',
      (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final admin = RecordingAdmin();
    final data = webProduct();
    (data['colors'] as List).first['imageUrls'] = [
      'assets/rear.webp',
      'assets/front.webp'
    ];
    await openForm(tester, admin, product: Wire.product(data));
    await tapVisible(tester, find.text('Red'));
    await tapVisible(tester, find.byTooltip('Move photo up').last);
    await tester.tap(find.text('Save colour'));
    await tester.pumpAndSettle();
    await tapVisible(tester, find.byTooltip('Move colour down').first);
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    expect(admin.attempts.single.colors.map((c) => c.id), ['blue', 'red']);
    expect(admin.attempts.single.colors.last.imageUrls,
        ['assets/front.webp', 'assets/rear.webp']);
    expect(admin.attempts.single.defaultColorId, 'blue');
    expect(tester.takeException(), isNull);
  });

  test(
      'all website page fields are exposed, with related models selected separately',
      () {
    expect(productPageGroups.values.expand((keys) => keys).toSet(),
        productPageDefaults.keys.where((key) => key != 'related_ids').toSet());
  });
}
