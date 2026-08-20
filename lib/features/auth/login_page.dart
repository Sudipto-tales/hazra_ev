import 'package:flutter/foundation.dart' show defaultTargetPlatform;
import 'package:flutter/material.dart';

import '../../core/config/api_config.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/api/api_exception.dart';
import '../../state/app_scope.dart';
import '../shell/main_shell.dart';

/// Sign-in screen.
///
/// Authenticates against `POST /auth/login`, which is the same route the admin
/// shell uses — the role comes back in `principal.type` rather than being
/// chosen here. With `--dart-define=USE_MOCKS=true` there is no client to call,
/// so any tap continues, which is what this screen did before.
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  // Pre-filled with the seeded development account from database/seed.php.
  final TextEditingController _email =
      TextEditingController(text: 'arif@hazra-ev.test');
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

        // One login route serves both shells, so an admin signing in here would
        // otherwise land in the employee UI with a token that cannot read it.
        if (session.isAdmin) {
          throw const ApiException(
            code: 'WRONG_SHELL',
            message: 'That is an admin account — use Restricted access instead.',
          );
        }
      } else {
        await Future<void>.delayed(const Duration(milliseconds: 600));
      }

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(builder: (_) => const MainShell()),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Employee access'),
      ),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (BuildContext context, BoxConstraints constraints) {
            return SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: Insets.xxl),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                // IntrinsicHeight bounds the Column's height so the Spacer
                // below can resolve inside an unbounded scroll view.
                child: IntrinsicHeight(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const SizedBox(height: Insets.xl),
                      Container(
                        width: 58,
                        height: 58,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: <Color>[
                              AppColors.primary,
                              AppColors.primaryDark,
                            ],
                          ),
                          borderRadius: BorderRadius.circular(Radii.lg),
                        ),
                        child: const Icon(
                          Icons.route_rounded,
                          color: Colors.white,
                          size: 30,
                        ),
                      ),
                      const SizedBox(height: Insets.xxl),
                      Text(
                        'Field Tracker',
                        style: theme.textTheme.displaySmall,
                      ),
                      const SizedBox(height: Insets.sm),
                      Text(
                        'Sign in to start your day, log company visits and '
                        'submit reports.',
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: theme.textTheme.bodyMedium?.color,
                        ),
                      ),
                      const SizedBox(height: Insets.xxxl),
                      // The API authenticates by email — employeeCode is a
                      // display identifier, not a credential.
                      Text('Email', style: theme.textTheme.labelLarge),
                      const SizedBox(height: Insets.sm),
                      TextField(
                        controller: _email,
                        textInputAction: TextInputAction.next,
                        keyboardType: TextInputType.emailAddress,
                        autocorrect: false,
                        decoration: const InputDecoration(
                          hintText: 'you@company.com',
                          prefixIcon: Icon(Icons.alternate_email_rounded),
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
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: () {},
                          child: const Text('Forgot password?'),
                        ),
                      ),
                      if (_error != null) ...<Widget>[
                        const SizedBox(height: Insets.sm),
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
                      const SizedBox(height: Insets.md),
                      SizedBox(
                        height: Sizes.primaryActionHeight,
                        child: FilledButton(
                          onPressed: _busy ? null : _signIn,
                          child: _busy
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Sign in'),
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
