import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app.dart';
import 'core/config/api_config.dart';
import 'data/api/api_client.dart';
import 'data/api/token_store.dart';
import 'data/company_directory.dart';
import 'data/mock/day_lock_store.dart';
import 'data/mock/product_store.dart';
import 'data/repositories/admin_repository.dart';
import 'data/repositories/employee_repository.dart';
import 'data/repositories/http_admin_repository.dart';
import 'data/repositories/http_employee_repository.dart';
import 'data/repositories/mock_admin_repository.dart';
import 'data/repositories/mock_employee_repository.dart';
import 'services/location_service.dart';
import 'state/app_scope.dart';
import 'state/notification_center.dart';
import 'state/settings_controller.dart';
import 'state/tracking_controller.dart';

/// Composition root.
///
/// Defaults to the live API at [ApiConfig.baseUrl] — `localhost:8000`. The
/// mock repositories are still wired and one flag away, so the UI can be
/// worked on with no server running:
///
/// ```sh
/// flutter run                                   # live API on localhost
/// flutter run --dart-define=USE_MOCKS=true      # offline, static fixtures
/// flutter run --dart-define=API_HOST=10.0.2.2   # Android emulator
/// ```
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setPreferredOrientations(<DeviceOrientation>[
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  debugPrint('[hazra-ev] ${ApiConfig.describe()}');

  final NotificationCenter notifications = NotificationCenter();

  final EmployeeRepository repository;
  final AdminRepository adminRepository;
  ApiClient? api;

  if (ApiConfig.useMocks) {
    // One catalogue, one notification feed and one day lock shared by both
    // repositories, so a product the admin lists is immediately visible on the
    // employee side — and a day the admin reopens is workable again. Against
    // the real API the server does this instead.
    final ProductStore productStore = ProductStore();
    final DayLockStore dayLock = DayLockStore();

    repository = MockEmployeeRepository(
      products: productStore,
      notifications: notifications,
      dayLock: dayLock,
    );
    adminRepository = MockAdminRepository(
      products: productStore,
      notifications: notifications,
      dayLock: dayLock,
    );
  } else {
    final TokenStore tokens = await TokenStore.open();

    api = ApiClient(tokens: tokens);

    // Both refresh attempts failed — the only honest thing left is to send the
    // user back to sign-in rather than fail every screen independently.
    api.onSessionExpired = () {
      appNavigatorKey.currentState?.popUntil((Route<dynamic> r) => r.isFirst);
    };

    repository = HttpEmployeeRepository(api);
    adminRepository = HttpAdminRepository(api);
  }

  // Id → name for companies and branches, backed by `GET /companies`. Built
  // from whichever repository is live so the mock build resolves names too.
  final CompanyDirectory companies =
      CompanyDirectory(() => repository.companies());

  // Real device GPS against the live API; simulated fixes only behind
  // USE_MOCKS. Tracking is the one feature where a mock left switched on would
  // silently invent the whole record of the day.
  final LocationService locationService =
      ApiConfig.useMocks ? MockLocationService() : GeoLocationService();
  final TrackingController tracking = TrackingController(
    repository: repository,
    locationService: locationService,
  );
  final SettingsController settings = await SettingsController.open();

  runApp(
    AppScope(
      repository: repository,
      adminRepository: adminRepository,
      companies: companies,
      locationService: locationService,
      tracking: tracking,
      settings: settings,
      notifications: notifications,
      api: api,
      child: const TrackingApp(),
    ),
  );
}
