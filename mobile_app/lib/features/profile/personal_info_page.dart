import 'package:flutter/material.dart';

import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/stat_tile.dart';
import '../../widgets/states.dart';

/// The full HR record behind the Profile header. Read-only by design — edits
/// go through HR, so this screen only ever renders `GET /api/employee/profile`.
class PersonalInfoPage extends StatefulWidget {
  const PersonalInfoPage({super.key});

  @override
  State<PersonalInfoPage> createState() => _PersonalInfoPageState();
}

class _PersonalInfoPageState extends State<PersonalInfoPage> {
  bool _loading = true;
  Object? _error;
  Employee? _me;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  /// Pushed from the Profile tab, which has already fetched this record — but
  /// it is refetched rather than passed in, so a deep link or a back-stack
  /// restore lands on a screen that can stand on its own.
  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final Employee me = await AppScope.of(context).repository.profile();
      if (!mounted) return;
      setState(() {
        _me = me;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
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
      body: _loading
          ? const Padding(
              padding: EdgeInsets.all(Insets.lg),
              child: LoadingCards(count: 3),
            )
          : _error != null || _me == null
              ? ErrorState(
                  onRetry: _load,
                  message: 'Your personal details could not be loaded. '
                      'Check your connection and try again.',
                )
              : _details(_me!),
    );
  }

  /// The record itself, once it is known to exist.
  Widget _details(Employee me) {
    return ListView(
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
