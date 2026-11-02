import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';

import 'core/config/api_config.dart';
import 'features/update/update_gate.dart';

import 'core/theme/app_theme.dart';
import 'features/admin/shell/admin_shell.dart';
import 'features/auth/role_select_page.dart';
import 'features/shell/main_shell.dart';
import 'state/app_scope.dart';

/// Lets `ApiClient.onSessionExpired` unwind to the sign-in screen from
/// outside the widget tree.
final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();

class TrackingApp extends StatelessWidget {
  const TrackingApp({super.key});

  @override
  Widget build(BuildContext context) {
    final AppScope scope = AppScope.of(context);

    return ListenableBuilder(
      listenable: scope.settings,
      builder: (BuildContext context, _) {
        return MaterialApp(
          title: 'Hazra EV',
          navigatorKey: appNavigatorKey,
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: scope.settings.themeMode,
          home: _resumeOrSignIn(scope),
          builder: (BuildContext context, Widget? child) {
            // Clamp OS font scaling so dense stat cards never break layout.
            final MediaQueryData mq = MediaQuery.of(context);
            return MediaQuery(
              data: mq.copyWith(
                textScaler: mq.textScaler.clamp(
                  minScaleFactor: 0.9,
                  maxScaleFactor: 1.25,
                ),
              ),
              child: !kIsWeb &&
                      defaultTargetPlatform == TargetPlatform.android &&
                      !ApiConfig.useMocks &&
                      scope.api != null
                  ? UpdateGate(
                      api: scope.api!, child: child ?? const SizedBox.shrink())
                  : child ?? const SizedBox.shrink(),
            );
          },
        );
      },
    );
  }

  /// The front door. A session persisted by [TokenStore] means this device
  /// already signed in, so skip [RoleSelectPage] and open straight into the
  /// shell its principal belongs to — that is what keeps a sign-in alive across
  /// relaunches for the refresh token's full 30-day life.
  ///
  /// It is optimistic on purpose: the access token may have aged out while the
  /// app was backgrounded, but the first authenticated call silently rotates it
  /// using the stored refresh token. Only if that refresh token is genuinely
  /// dead (revoked, or past its 30 days) does [ApiClient.onSessionExpired] fire
  /// and bounce back here to [RoleSelectPage].
  Widget _resumeOrSignIn(AppScope scope) {
    final session = scope.api?.session;
    if (session == null) {
      return const RoleSelectPage();
    }
    return session.isAdmin ? const AdminShell() : const MainShell();
  }
}
