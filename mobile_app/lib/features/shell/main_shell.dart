import 'package:flutter/material.dart';

import '../../state/app_scope.dart';
import '../calendar/calendar_page.dart';
import '../home/home_page.dart';
import '../profile/profile_page.dart';
import '../reports/reports_page.dart';

/// Lets any descendant jump to another tab (Home → "View all reports").
class ShellScope extends InheritedWidget {
  const ShellScope({super.key, required this.goToTab, required super.child});

  final void Function(int index) goToTab;

  static ShellScope? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<ShellScope>();

  @override
  bool updateShouldNotify(ShellScope oldWidget) => false;
}

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    // Kick off the workday snapshot once for the whole session.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      AppScope.of(context).tracking.load();
    });
  }

  void _goToTab(int index) => setState(() => _index = index);

  @override
  Widget build(BuildContext context) {
    return ShellScope(
      goToTab: _goToTab,
      child: Scaffold(
        body: IndexedStack(
          index: _index,
          children: const <Widget>[
            HomePage(),
            ReportsPage(),
            CalendarPage(),
            ProfilePage(),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: _goToTab,
          destinations: const <NavigationDestination>[
            NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home_rounded),
              label: 'Home',
            ),
            NavigationDestination(
              icon: Icon(Icons.description_outlined),
              selectedIcon: Icon(Icons.description_rounded),
              label: 'Reports',
            ),
            NavigationDestination(
              icon: Icon(Icons.calendar_month_outlined),
              selectedIcon: Icon(Icons.calendar_month_rounded),
              label: 'Calendar',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline_rounded),
              selectedIcon: Icon(Icons.person_rounded),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }
}
