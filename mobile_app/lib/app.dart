import 'package:flutter/material.dart';

import 'core/theme/app_theme.dart';
import 'features/auth/role_select_page.dart';
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
          title: 'Field Tracker',
          navigatorKey: appNavigatorKey,
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: scope.settings.themeMode,
          home: const RoleSelectPage(),
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
              child: child ?? const SizedBox.shrink(),
            );
          },
        );
      },
    );
  }
}
