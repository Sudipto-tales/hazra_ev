import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';

/// Shows a generated password, once.
///
/// The server hashes it and hands the plaintext back on exactly one response.
/// Nothing stores it and no route will return it again, so if this dialog is
/// dismissed without the admin taking the value down, the only way forward is a
/// reset. That is why it is a modal the admin has to acknowledge rather than a
/// snackbar that slides away on its own, and why it does not close on a tap
/// outside.
Future<void> showCredentialDialog(
  BuildContext context, {
  required String name,
  required String email,
  required String password,
}) {
  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (BuildContext context) => _CredentialDialog(
      name: name,
      email: email,
      password: password,
    ),
  );
}

class _CredentialDialog extends StatelessWidget {
  const _CredentialDialog({
    required this.name,
    required this.email,
    required this.password,
  });

  final String name;
  final String email;
  final String password;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AlertDialog(
      icon: const Icon(Icons.key_rounded, size: 28),
      title: const Text('Sign-in details'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Give these to $name. This password is shown once — after you '
            'close this it cannot be looked up, only reset.',
            style: theme.textTheme.bodyMedium,
          ),
          const SizedBox(height: Insets.lg),
          _Row(label: 'Email', value: email),
          const SizedBox(height: Insets.sm),
          _Row(label: 'Password', value: password, mono: true),
        ],
      ),
      actions: <Widget>[
        TextButton.icon(
          onPressed: () async {
            await Clipboard.setData(
              ClipboardData(text: '$email\n$password'),
            );
            if (!context.mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Sign-in details copied')),
            );
          },
          icon: const Icon(Icons.copy_rounded, size: 18),
          label: const Text('Copy'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Saved it'),
        ),
      ],
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value, this.mono = false});

  final String label;
  final String value;
  final bool mono;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(
        horizontal: Insets.md,
        vertical: Insets.sm,
      ),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(Radii.sm),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            label,
            style: theme.textTheme.labelSmall?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 2),
          SelectableText(
            value,
            style: mono
                ? theme.textTheme.titleMedium?.copyWith(
                    fontFamily: 'monospace',
                    letterSpacing: 1.2,
                  )
                : theme.textTheme.bodyLarge,
          ),
        ],
      ),
    );
  }
}
