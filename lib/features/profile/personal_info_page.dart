import 'package:flutter/material.dart';

import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/mock/mock_data.dart';
import '../../data/models/models.dart';
import '../../widgets/app_card.dart';
import '../../widgets/stat_tile.dart';

class PersonalInfoPage extends StatelessWidget {
  const PersonalInfoPage({super.key});

  @override
  Widget build(BuildContext context) {
    final Employee me = MockData.employee;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Personal information'),
        actions: <Widget>[
          TextButton(
            onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Editing is handled by HR')),
            ),
            child: const Text('Request edit'),
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          const _Group(
            title: 'Identity',
            children: <Widget>[],
          ),
          AppCard(
            child: Column(
              children: <Widget>[
                KeyValueRow(
                  label: 'Full name',
                  value: me.name,
                  icon: Icons.person_outline_rounded,
                ),
                KeyValueRow(
                  label: 'Employee ID',
                  value: me.employeeCode,
                  icon: Icons.badge_outlined,
                ),
                KeyValueRow(
                  label: 'Designation',
                  value: me.designation,
                  icon: Icons.work_outline_rounded,
                ),
                KeyValueRow(
                  label: 'Department',
                  value: Fmt.orDash(me.department),
                  icon: Icons.apartment_rounded,
                ),
                KeyValueRow(
                  label: 'Region',
                  value: Fmt.orDash(me.region),
                  icon: Icons.map_outlined,
                ),
                KeyValueRow(
                  label: 'Reporting to',
                  value: me.reportingTo,
                  icon: Icons.supervisor_account_outlined,
                ),
                KeyValueRow(
                  label: 'Joined on',
                  value: Fmt.mediumDate(me.joinedOn),
                  icon: Icons.event_outlined,
                ),
              ],
            ),
          ),
          const SectionHeader(
            title: 'Contact',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: Column(
              children: <Widget>[
                KeyValueRow(
                  label: 'Email',
                  value: me.email,
                  icon: Icons.mail_outline_rounded,
                ),
                KeyValueRow(
                  label: 'Phone',
                  value: me.phone,
                  icon: Icons.phone_outlined,
                ),
                KeyValueRow(
                  label: 'Address',
                  value: me.address,
                  icon: Icons.home_outlined,
                ),
                KeyValueRow(
                  label: 'Blood group',
                  value: me.bloodGroup,
                  icon: Icons.bloodtype_outlined,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Group extends StatelessWidget {
  const _Group({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(title, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: Insets.md),
        ...children,
      ],
    );
  }
}
