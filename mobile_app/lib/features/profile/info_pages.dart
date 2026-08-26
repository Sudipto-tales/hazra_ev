import 'package:flutter/material.dart';

import '../../core/config/tracking_config.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../widgets/app_card.dart';
import '../../widgets/stat_tile.dart';

/// Simple content pages reached from Profile → Support / Privacy.

class PrivacyPage extends StatelessWidget {
  const PrivacyPage({super.key});

  @override
  Widget build(BuildContext context) {
    const TrackingConfig config = TrackingConfig.defaults;
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Privacy')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('When location is recorded',
                    style: theme.textTheme.titleLarge),
                const SizedBox(height: Insets.sm),
                Text(
                  'Your location is recorded only while a work session is open — '
                  'between Start Day and End Day. Closing a session stops '
                  'collection immediately, and nothing is recorded on days you '
                  'do not start.',
                  style: theme.textTheme.bodyLarge,
                ),
              ],
            ),
          ),
          const SectionHeader(
            title: 'What is stored',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: Column(
              children: <Widget>[
                const KeyValueRow(
                  label: 'GPS coordinates',
                  value: 'Yes',
                  icon: Icons.place_outlined,
                ),
                KeyValueRow(
                  label: 'Sampling interval',
                  value: 'Every ${config.locationIntervalSeconds}s',
                  icon: Icons.timer_outlined,
                ),
                const KeyValueRow(
                  label: 'Accuracy & speed',
                  value: 'Yes',
                  icon: Icons.speed_rounded,
                ),
                const KeyValueRow(
                  label: 'Reports & photos',
                  value: 'Yes',
                  icon: Icons.description_outlined,
                ),
                const KeyValueRow(
                  label: 'Contacts, messages, media',
                  value: 'Never',
                  icon: Icons.block_rounded,
                  valueColor: AppColors.success,
                ),
              ],
            ),
          ),
          const SectionHeader(
            title: 'Who can see it',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: Text(
              'Your zonal manager and the admin panel can see your route, stops, '
              'company visits and reports for working days. Colleagues cannot.',
              style: theme.textTheme.bodyLarge,
            ),
          ),
        ],
      ),
    );
  }
}

class HelpPage extends StatelessWidget {
  const HelpPage({super.key});

  static const List<(String, String)> _faq = <(String, String)>[
    (
      'Why can I not start my day?',
      'Device location must be switched on and permission granted. Your day '
          'cannot start without a valid GPS fix, because the first fix becomes '
          'your joining time.'
    ),
    (
      'I lost internet in the field — is my data gone?',
      'No. Locations and reports are stored on your device and upload '
          'automatically once you are back online. The tracking sheet shows how '
          'many items are queued.'
    ),
    (
      'Can I submit more than one report for the same company?',
      'Yes. Every report is an independent record — submit as many as the visit '
          'needs.'
    ),
    (
      'What is a session?',
      'A continuous span of tracked work. Taking a break closes the session; '
          'resuming opens a new one. Your day can hold several.'
    ),
    (
      'What counts as a stop?',
      'Staying inside a small radius, below walking speed, for longer than the '
          'configured threshold. Stops can then be linked to a company visit.'
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Help & support')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          ..._faq.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: Insets.md),
              child: AppCard(
                padding: EdgeInsets.zero,
                child: ExpansionTile(
                  shape: const Border(),
                  collapsedShape: const Border(),
                  tilePadding: const EdgeInsets.symmetric(
                    horizontal: Insets.lg,
                  ),
                  childrenPadding: const EdgeInsets.fromLTRB(
                    Insets.lg,
                    0,
                    Insets.lg,
                    Insets.lg,
                  ),
                  title: Text(
                    item.$1,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  children: <Widget>[
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        item.$2,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: Insets.lg),
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Still stuck?',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: Insets.sm),
                const KeyValueRow(
                  label: 'Support email',
                  value: 'support@company.com',
                  icon: Icons.mail_outline_rounded,
                ),
                const KeyValueRow(
                  label: 'Support hotline',
                  value: '+880 9600 123456',
                  icon: Icons.phone_outlined,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class AboutPage extends StatelessWidget {
  const AboutPage({super.key});

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('About')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.xxl,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          Center(
            child: Column(
              children: <Widget>[
                Container(
                  width: 66,
                  height: 66,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: <Color>[AppColors.primary, AppColors.primaryDark],
                    ),
                    borderRadius: BorderRadius.circular(Radii.lg),
                  ),
                  child: const Icon(Icons.route_rounded,
                      color: Colors.white, size: 33),
                ),
                const SizedBox(height: Insets.lg),
                Text('Field Tracker', style: theme.textTheme.headlineSmall),
                const SizedBox(height: 4),
                Text('Version 0.1.0 · build 1',
                    style: theme.textTheme.bodySmall),
              ],
            ),
          ),
          const SizedBox(height: Insets.xxl),
          AppCard(
            child: Column(
              children: <Widget>[
                const KeyValueRow(
                  label: 'Data source',
                  value: 'Static demo data',
                  icon: Icons.dataset_outlined,
                ),
                const KeyValueRow(
                  label: 'Backend',
                  value: 'Not connected',
                  icon: Icons.cloud_off_outlined,
                ),
                const KeyValueRow(
                  label: 'Tracking engine',
                  value: 'Mock location service',
                  icon: Icons.my_location_rounded,
                ),
                KeyValueRow(
                  label: 'Support',
                  value: 'support@company.com',
                  icon: Icons.mail_outline_rounded,
                  valueColor: theme.colorScheme.primary,
                ),
              ],
            ),
          ),
          const SizedBox(height: Insets.lg),
          Text(
            '© 2026 Company Ltd. All rights reserved.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}
