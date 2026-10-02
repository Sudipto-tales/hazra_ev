import 'package:flutter/material.dart';

import '../dashboard/admin_dashboard_page.dart';
import '../employees/employees_page.dart';
import '../insights/insights_page.dart';
import '../profile/admin_profile_page.dart';
import '../reports/admin_reports_page.dart';

/// Lets any descendant jump to another admin tab (dashboard KPI → inbox).
class AdminShellScope extends InheritedWidget {
  const AdminShellScope({
    super.key,
    required this.goToTab,
    required super.child,
  });

  final void Function(int index) goToTab;

  static AdminShellScope? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<AdminShellScope>();

  @override
  bool updateShouldNotify(AdminShellScope oldWidget) => false;
}

/// Five tabs. The management screens (add employee, assignments, tracking
/// rules) are deliberately pushed routes behind the Admin tab rather than
/// destinations — they are infrequent and destination-style, exactly how the
/// employee Profile tab treats Settings.
class AdminShell extends StatefulWidget {
  const AdminShell({super.key});

  @override
  State<AdminShell> createState() => _AdminShellState();
}

class _AdminShellState extends State<AdminShell> {
  int _index = 0;

  void _goToTab(int index) => setState(() => _index = index);

  @override
  Widget build(BuildContext context) {
    return AdminShellScope(
      goToTab: _goToTab,
      child: Scaffold(
        body: IndexedStack(
          index: _index,
          children: const <Widget>[
            AdminDashboardPage(),
            EmployeesPage(),
            AdminReportsPage(),
            InsightsPage(),
            AdminProfilePage(),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: _goToTab,
          destinations: const <NavigationDestination>[
            NavigationDestination(
              icon: Icon(Icons.dashboard_outlined),
              selectedIcon: Icon(Icons.dashboard_rounded),
              label: 'Dashboard',
            ),
            NavigationDestination(
              icon: Icon(Icons.groups_outlined),
              selectedIcon: Icon(Icons.groups_rounded),
              label: 'Team',
            ),
            NavigationDestination(
              icon: Icon(Icons.inbox_outlined),
              selectedIcon: Icon(Icons.inbox_rounded),
              label: 'Reports',
            ),
            NavigationDestination(
              icon: Icon(Icons.insights_outlined),
              selectedIcon: Icon(Icons.insights_rounded),
              label: 'Insights',
            ),
            NavigationDestination(
              icon: Icon(Icons.admin_panel_settings_outlined),
              selectedIcon: Icon(Icons.admin_panel_settings_rounded),
              label: 'Admin',
            ),
          ],
        ),
      ),
    );
  }
}
