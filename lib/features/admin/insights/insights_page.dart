import 'package:flutter/material.dart';

import 'team_analytics_tab.dart';
import 'team_attendance_tab.dart';

/// Two team-wide views behind one tab, mirroring how the employee calendar
/// splits Attendance and Statistics.
class InsightsPage extends StatelessWidget {
  const InsightsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Insights'),
          bottom: const TabBar(
            tabs: <Widget>[
              Tab(text: 'Attendance'),
              Tab(text: 'Analytics'),
            ],
          ),
        ),
        body: const TabBarView(
          children: <Widget>[
            TeamAttendanceTab(),
            TeamAnalyticsTab(),
          ],
        ),
      ),
    );
  }
}
