import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/api/api_exception.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/states.dart';

/// `POST /me/password`.
///
/// Knowing the current password is the whole authorisation — an access token on
/// its own must not be enough to lock the owner out of their own account. The
/// server signs out every *other* device on success and leaves this one alone.
class ChangePasswordPage extends StatefulWidget {
  const ChangePasswordPage({super.key});

  @override
  State<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends State<ChangePasswordPage> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  final TextEditingController _current = TextEditingController();
  final TextEditingController _next = TextEditingController();
  final TextEditingController _confirm = TextEditingController();

  bool _busy = false;
  bool _obscure = true;

  /// The server's refusal, shown in the form rather than as a snackbar: the
  /// user has a field to correct, and a snackbar is gone before they read it.
  String? _error;

  /// Matches Password::MIN_LENGTH on the server. Failing here saves a round
  /// trip; the server enforces it either way.
  static const int _minLength = 8;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await AppScope.of(context).repository.changePassword(
            current: _current.text,
            next: _next.text,
          );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        // 403 is specifically "current password is wrong" — the token is fine,
        // so this must not read as a session problem.
        _error = e.isForbidden ? 'That is not your current password.' : e.message;
      });
      return;
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = 'Could not change your password. $e';
      });
      return;
    }

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Password changed. Your other devices were signed out.'),
      ),
    );
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Change password')),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.lg),
          child: SizedBox(
            height: Sizes.primaryActionHeight,
            child: FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Change password'),
            ),
          ),
        ),
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            Insets.lg,
            Insets.lg,
            Insets.lg,
            Insets.xxxl,
          ),
          children: <Widget>[
            if (_error != null) ...<Widget>[
              AlertBanner(
                icon: Icons.error_outline_rounded,
                title: 'Not changed',
                message: _error!,
                tone: AppColors.danger,
              ),
              const SizedBox(height: Insets.lg),
            ],
            AppCard(
              child: Column(
                children: <Widget>[
                  _field(
                    controller: _current,
                    label: 'Current password',
                    icon: Icons.lock_outline_rounded,
                    validator: (String? v) =>
                        (v ?? '').isEmpty ? 'Required' : null,
                  ),
                  _field(
                    controller: _next,
                    label: 'New password',
                    icon: Icons.lock_reset_rounded,
                    helper: 'At least $_minLength characters',
                    validator: (String? v) {
                      final String value = v ?? '';
                      if (value.length < _minLength) {
                        return 'At least $_minLength characters';
                      }
                      if (value == _current.text) {
                        return 'Must differ from your current password';
                      }
                      return null;
                    },
                  ),
                  _field(
                    controller: _confirm,
                    label: 'Confirm new password',
                    icon: Icons.check_circle_outline_rounded,
                    validator: (String? v) =>
                        (v ?? '') == _next.text ? null : 'Does not match',
                  ),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () => setState(() => _obscure = !_obscure),
                      icon: Icon(
                        _obscure
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                        size: 18,
                      ),
                      label: Text(_obscure ? 'Show' : 'Hide'),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),
            Text(
              'Your other devices will be signed out. This one stays signed '
              'in. If you have forgotten your password, ask your admin to '
              'reset it — it cannot be looked up.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    String? Function(String?)? validator,
    String? helper,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: TextFormField(
        controller: controller,
        validator: validator,
        obscureText: _obscure,
        autocorrect: false,
        enableSuggestions: false,
        decoration: InputDecoration(
          labelText: label,
          helperText: helper,
          prefixIcon: Icon(icon),
        ),
      ),
    );
  }
}
