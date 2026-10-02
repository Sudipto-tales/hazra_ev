import 'package:flutter/foundation.dart' show defaultTargetPlatform;
import 'package:flutter/material.dart';

import '../../core/config/api_config.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/theme/theme_ext.dart';
import '../../data/api/api_exception.dart';
import '../../state/app_scope.dart';
import '../../widgets/states.dart';
import '../admin/shell/admin_shell.dart';

/// Admin sign-in. Deliberately styled apart from the employee form so the
/// restricted framing is obvious.
///
/// Same `POST /auth/login` route the employee form uses — the split here is a
/// UI affordance, and the token's role is what actually decides what the
/// session can read.
class AdminLoginPage extends StatefulWidget {
  const AdminLoginPage({super.key});

  @override
  State<AdminLoginPage> createState() => _AdminLoginPageState();
}

class _AdminLoginPageState extends State<AdminLoginPage> {
  // Pre-filled with the seeded development account from database/seed.php.
  final TextEditingController _email =
      TextEditingController(text: 'admin@hazra-ev.test');
  final TextEditingController _password =
      TextEditingController(text: 'password123');
  bool _obscure = true;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    final AppScope scope = AppScope.of(context);

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      if (scope.isLive) {
        final session = await scope.api!.login(
          email: _email.text.trim(),
          password: _password.text,
          device: <String, dynamic>{
            'platform': defaultTargetPlatform.name,
            'appVersion': '0.1.0',
          },
        );

        // An employee token would 403 on every screen behind this one, so the
        // refusal belongs here rather than as a wall of failed requests.
        if (!session.isAdmin) {
          await scope.api!.logout();
          throw const ApiException(
            code: 'NOT_ADMIN',
            message: 'That account is not an admin. Use Employee access.',
          );
        }
      } else {
        await Future<void>.delayed(const Duration(milliseconds: 600));
      }

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(builder: (_) => const AdminShell()),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.message;
      });
    } catch (e) {
      // Anything that is not an ApiException — a TypeError from a malformed
      // payload, a platform channel failure — used to leave _busy true and the
      // button spinning with no explanation. Never hang on an unknown fault.
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = 'Sign-in failed unexpectedly. $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool dark = context.isDark;

    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Restricted access'),
      ),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (BuildContext context, BoxConstraints constraints) {
            return SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: Insets.xxl),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: IntrinsicHeight(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const SizedBox(height: Insets.xl),
                      Container(
                        width: 58,
                        height: 58,
                        decoration: BoxDecoration(
                          color: dark
                              ? AppColors.danger.withValues(alpha: 0.18)
                              : AppColors.dangerSoft,
                          borderRadius: BorderRadius.circular(Radii.lg),
                        ),
                        child: const Icon(
                          Icons.shield_outlined,
                          color: AppColors.danger,
                          size: 30,
                        ),
                      ),
                      const SizedBox(height: Insets.xxl),
                      Text('Admin console', style: theme.textTheme.displaySmall),
                      const SizedBox(height: Insets.sm),
                      Text(
                        'Monitor the field team, review routes and approve '
                        'submitted reports.',
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: theme.textTheme.bodyMedium?.color,
                        ),
                      ),
                      const SizedBox(height: Insets.xl),
                      const AlertBanner(
                        icon: Icons.lock_rounded,
                        title: 'Authorized personnel only',
                        message:
                            'Every sign-in and every employee record you open '
                            'is written to the access log.',
                        tone: AppColors.danger,
                      ),
                      const SizedBox(height: Insets.xxl),
                      // adminCode is a display identifier; the API
                      // authenticates by email.
                      Text('Email', style: theme.textTheme.labelLarge),
                      const SizedBox(height: Insets.sm),
                      TextField(
                        controller: _email,
                        textInputAction: TextInputAction.next,
                        keyboardType: TextInputType.emailAddress,
                        autocorrect: false,
                        decoration: const InputDecoration(
                          hintText: 'admin@company.com',
                          prefixIcon: Icon(Icons.admin_panel_settings_outlined),
                        ),
                      ),
                      const SizedBox(height: Insets.lg),
                      Text('Password', style: theme.textTheme.labelLarge),
                      const SizedBox(height: Insets.sm),
                      TextField(
                        controller: _password,
                        obscureText: _obscure,
                        decoration: InputDecoration(
                          hintText: 'Your password',
                          prefixIcon: const Icon(Icons.lock_outline_rounded),
                          suffixIcon: IconButton(
                            onPressed: () =>
                                setState(() => _obscure = !_obscure),
                            icon: Icon(
                              _obscure
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                            ),
                          ),
                        ),
                      ),
                      if (_error != null) ...<Widget>[
                        const SizedBox(height: Insets.md),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Icon(
                              Icons.error_outline_rounded,
                              size: 18,
                              color: theme.colorScheme.error,
                            ),
                            const SizedBox(width: Insets.sm),
                            Expanded(
                              child: Text(
                                _error!,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.error,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                      const SizedBox(height: Insets.xl),
                      SizedBox(
                        height: Sizes.primaryActionHeight,
                        child: FilledButton(
                          onPressed: _busy ? null : _signIn,
                          style: FilledButton.styleFrom(
                            backgroundColor: AppColors.danger,
                          ),
                          child: _busy
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Enter admin console'),
                        ),
                      ),
                      const Spacer(),
                      Center(
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: Insets.xl),
                          child: Text(
                            'Version 0.1.0 · ${ApiConfig.describe()}',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.bodySmall,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
