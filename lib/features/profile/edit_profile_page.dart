import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/api/api_exception.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/states.dart';

/// The two fields an employee may change about themselves.
///
/// `PATCH /me` accepts `phone` and `avatarUrl` and nothing else — it answers a
/// body with any other key with a validation failure. Name, designation,
/// department, region and address are admin-owned and are edited through
/// `PATCH /employees/{id}`, which is not a route this app's employee role can
/// call. The screen says so rather than offering fields that would be refused.
///
/// Avatar is a URL, not an upload: there is no image endpoint for it. The
/// field is a plain text input on purpose — pretending to offer a photo picker
/// would be a promise the API cannot keep.
class EditProfilePage extends StatefulWidget {
  const EditProfilePage({super.key});

  @override
  State<EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<EditProfilePage> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _avatar = TextEditingController();

  Employee? _me;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _phone.dispose();
    _avatar.dispose();
    super.dispose();
  }

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
        _phone.text = me.phone;
        _avatar.text = me.avatarUrl;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e is ApiException ? e.message : 'Could not load your profile.';
        _loading = false;
      });
    }
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;

    final Employee? me = _me;
    if (me == null) return;

    final String phone = _phone.text.trim();
    final String avatar = _avatar.text.trim();

    // Send only what actually changed. An unchanged field is not a field the
    // server needs to hear about, and an empty patch is a validation error.
    final String? phoneChange = phone == me.phone ? null : phone;
    final String? avatarChange = avatar == me.avatarUrl ? null : avatar;

    if (phoneChange == null && avatarChange == null) {
      Navigator.of(context).pop();
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final Employee updated = await AppScope.of(context)
          .repository
          .updateProfile(phone: phoneChange, avatarUrl: avatarChange);

      if (!mounted) return;
      Navigator.of(context).pop(updated);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = e is ApiException ? e.message : 'Could not save your changes.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Edit profile')),
      body: switch ((_loading, _me)) {
        (true, _) => const LoadingCards(count: 2),
        (false, null) => ErrorState(
            message: _error ?? 'Could not load your profile.',
            onRetry: _load,
          ),
        (false, _) => Form(
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
                    title: 'Could not save',
                    message: _error!,
                    tone: AppColors.danger,
                  ),
                  const SizedBox(height: Insets.lg),
                ],
                AppCard(
                  child: Column(
                    children: <Widget>[
                      TextFormField(
                        controller: _phone,
                        enabled: !_saving,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(
                          labelText: 'Phone',
                          prefixIcon: Icon(Icons.phone_outlined),
                        ),
                        validator: (String? v) =>
                            (v == null || v.trim().isEmpty)
                                ? 'A phone number is required'
                                : null,
                      ),
                      const SizedBox(height: Insets.lg),
                      TextFormField(
                        controller: _avatar,
                        enabled: !_saving,
                        keyboardType: TextInputType.url,
                        decoration: const InputDecoration(
                          labelText: 'Photo URL',
                          helperText: 'A link to an image. Leave blank to use '
                              'your initials.',
                          prefixIcon: Icon(Icons.link_rounded),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: Insets.lg),
                Text(
                  'Everything else on your record — name, designation, '
                  'department, region and address — is maintained by your '
                  'admin. Ask them to change it.',
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(height: Insets.xl),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Save changes'),
                ),
              ],
            ),
          ),
      },
    );
  }
}
