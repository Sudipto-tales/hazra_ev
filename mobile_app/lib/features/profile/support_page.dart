import 'package:flutter/foundation.dart' show defaultTargetPlatform;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/stat_tile.dart';
import 'app_info.dart';

/// Where to reach a human. Kept next to the screens that show it rather than
/// in `core/`, because these are content, not configuration — when the numbers
/// change it is a copy edit.
///
/// The same two values also appear in `HelpPage` / `AboutPage`; folding all
/// three onto these constants is a follow-up for whoever owns `info_pages.dart`.
class SupportContacts {
  const SupportContacts._();

  static const String email = 'support@company.com';
  static const String hotline = '+880 9600 123456';
  static const String hours = 'Sunday–Thursday, 9:00–18:00';
}

/// Support contact details, made usable rather than decorative.
///
/// There is no support API to post to, and the app deliberately carries no
/// `url_launcher`, so tapping cannot open a mail app. What it can do is put
/// the address on the clipboard, which is the whole reason anyone taps a
/// support row on a phone.
class ContactSupportPage extends StatelessWidget {
  const ContactSupportPage({super.key});

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Contact support')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          AppCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: <Widget>[
                const CopyableRow(
                  icon: Icons.mail_outline_rounded,
                  label: 'Support email',
                  value: SupportContacts.email,
                ),
                Divider(height: 1, color: theme.dividerColor),
                const CopyableRow(
                  icon: Icons.phone_outlined,
                  label: 'Support hotline',
                  value: SupportContacts.hotline,
                ),
              ],
            ),
          ),
          const SizedBox(height: Insets.md),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Insets.xs),
            child: Text(
              'The hotline is answered ${SupportContacts.hours}. Outside those '
              'hours email is faster — include your employee ID and what you '
              'were doing when the problem happened.',
              style: theme.textTheme.bodySmall,
            ),
          ),
          const SectionHeader(
            title: 'Before you write in',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: Text(
              'Anything you recorded offline is safe on the phone and uploads '
              'by itself once you have signal — a report that has not appeared '
              'on the server yet is not a report that was lost. Settings → '
              'Location status shows what is still queued.',
              style: theme.textTheme.bodyMedium,
            ),
          ),
        ],
      ),
    );
  }
}

/// "Report a problem" without a bug-report endpoint to post to.
///
/// The useful half of a bug report is the part the user cannot write down:
/// build number, platform, whether the app is on live data or the demo
/// fixtures. This page assembles exactly that, and hands it over as one block
/// of text to paste into an email — which needs no API and no dependency.
class ReportProblemPage extends StatelessWidget {
  const ReportProblemPage({super.key});

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppScope scope = AppScope.of(context);
    final DateTime now = DateTime.now();

    final Map<String, String> details = <String, String>{
      'App version': AppInfo.label,
      'Platform': defaultTargetPlatform.name,
      'Data source': scope.isLive ? 'Live server' : 'Demo data (no server)',
      'Prepared': '${Fmt.mediumDate(now)} ${Fmt.time(now)}',
    };

    return Scaffold(
      appBar: AppBar(title: const Text('Report a problem')),
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
                Text('How to report', style: theme.textTheme.titleLarge),
                const SizedBox(height: Insets.sm),
                Text(
                  'Email ${SupportContacts.email} with what you expected, what '
                  'happened instead, and the time it happened. Copy the '
                  'details below into the same message — they answer the first '
                  'three questions support would otherwise have to ask.',
                  style: theme.textTheme.bodyMedium,
                ),
              ],
            ),
          ),
          const SectionHeader(
            title: 'Details to include',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: Column(
              children: <Widget>[
                for (final MapEntry<String, String> e in details.entries)
                  KeyValueRow(label: e.key, value: e.value, dense: true),
              ],
            ),
          ),
          const SizedBox(height: Insets.lg),
          SizedBox(
            height: Sizes.primaryActionHeight,
            child: FilledButton.icon(
              onPressed: () => _copy(
                context,
                <String>[
                  'Problem report — Field Tracker',
                  ...details.entries.map(
                    (MapEntry<String, String> e) => '${e.key}: ${e.value}',
                  ),
                  '',
                  'What I expected:',
                  'What happened:',
                ].join('\n'),
                'Details copied — paste them into your email',
              ),
              icon: const Icon(Icons.copy_all_rounded),
              label: const Text('Copy details'),
            ),
          ),
          const SizedBox(height: Insets.md),
          const AppCard(
            padding: EdgeInsets.zero,
            child: CopyableRow(
              icon: Icons.mail_outline_rounded,
              label: 'Send to',
              value: SupportContacts.email,
            ),
          ),
          const SizedBox(height: Insets.md),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Insets.xs),
            child: Text(
              'Nothing is sent from this screen. The app has no channel to '
              'submit a bug directly, so the email is the report.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textTertiary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// A value row whose whole point is getting the value off the screen and into
/// a message — tap anywhere, or use the button.
class CopyableRow extends StatelessWidget {
  const CopyableRow({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return ListTile(
      leading: Icon(icon, size: 20, color: AppColors.primary),
      title: Text(label, style: theme.textTheme.bodySmall),
      subtitle: Text(value, style: theme.textTheme.titleMedium),
      trailing: const Icon(Icons.copy_rounded, size: 18),
      onTap: () => _copy(context, value, '$label copied'),
    );
  }
}

Future<void> _copy(BuildContext context, String text, String confirmation) async {
  await Clipboard.setData(ClipboardData(text: text));
  if (!context.mounted) return;
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(confirmation)),
  );
}
