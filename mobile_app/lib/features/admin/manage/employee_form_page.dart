import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/api/api_exception.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/select_field.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../widgets/credential_dialog.dart';

/// Add or edit a field employee. One page for both — a null [employee] means
/// create, mirroring how [EmployeeDraft] treats a null id.
class EmployeeFormPage extends StatefulWidget {
  const EmployeeFormPage({super.key, this.employee});

  final Employee? employee;

  @override
  State<EmployeeFormPage> createState() => _EmployeeFormPageState();
}

class _EmployeeFormPageState extends State<EmployeeFormPage> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();

  late final TextEditingController _name =
      TextEditingController(text: widget.employee?.name ?? '');

  /// Empty on create — deliberately not pre-filled. See [_codeHelper].
  late final TextEditingController _code =
      TextEditingController(text: widget.employee?.employeeCode ?? '');
  late final TextEditingController _designation =
      TextEditingController(text: widget.employee?.designation ?? '');
  late final TextEditingController _email =
      TextEditingController(text: widget.employee?.email ?? '');
  late final TextEditingController _phone =
      TextEditingController(text: widget.employee?.phone ?? '');
  late final TextEditingController _address =
      TextEditingController(text: widget.employee?.address ?? '');

  /// Create only, and always empty. A password cannot be read back, so there is
  /// nothing to pre-fill an edit with — the edit form does not show this field
  /// at all, and a reset lives on the employee detail page instead.
  final TextEditingController _password = TextEditingController();

  /// Department and region are free text and optional: a new hire is often
  /// added before the org placement is decided, and the zone names are not a
  /// closed list. Search still matches them — [MockAdminRepository.team]
  /// filters on whatever string is stored.
  late final TextEditingController _department =
      TextEditingController(text: widget.employee?.department ?? '');
  late final TextEditingController _region =
      TextEditingController(text: widget.employee?.region ?? '');

  late String? _bloodGroup = (widget.employee?.bloodGroup.isEmpty ?? true)
      ? null
      : widget.employee!.bloodGroup;
  late DateTime _joinedOn = widget.employee?.joinedOn ?? DateTime.now();

  bool _busy = false;

  /// The signed-in admin, who becomes the new employee's reporting manager.
  /// Fetched rather than assumed: the console can be signed into by any admin
  /// in the org, and the record has to name the one who is actually here.
  AdminUser? _admin;
  bool _loading = true;
  Object? _loadError;

  /// Server-side refusal of the last save (`EMPLOYEE_CODE_TAKEN`,
  /// validation, network). Shown in the form, not as a snackbar — the admin
  /// has to correct a field, and a snackbar is gone before they can read it.
  String? _saveError;

  bool get _isCreate => widget.employee == null;

  /// There is no auto-suggested employee code any more, and that is on
  /// purpose. The old suggestion was `EMP-${1050 + AdminMockData.employees
  /// .length}` — a count of the *mock* roster, which against live data
  /// suggests a code that already belongs to someone. Deriving it from the
  /// real roster does not fix it either: `adminRepository.team()` is a
  /// single page (limit 200) with no total exposed to the caller, so the app
  /// cannot know it has seen every code, and the codes it can see are not
  /// necessarily the highest ones. A confidently wrong ID that the admin
  /// accepts without reading is worse than an empty field they have to fill
  /// in. The server holds the real uniqueness constraint and answers
  /// `EMPLOYEE_CODE_TAKEN`, which [_save] surfaces.
  static const String _codeHelper = 'Format EMP-1042 · must be unused';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final AdminUser admin =
          await AppScope.of(context).adminRepository.profile();
      if (!mounted) return;
      setState(() {
        _admin = admin;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loadError = e;
        _loading = false;
      });
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    _designation.dispose();
    _email.dispose();
    _phone.dispose();
    _address.dispose();
    _department.dispose();
    _region.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    final AdminUser? admin = _admin;
    if (admin == null) return;

    setState(() {
      _busy = true;
      _saveError = null;
    });

    final EmployeeSaveResult saved;

    try {
      saved = await AppScope.of(context).adminRepository.saveEmployee(
            EmployeeDraft(
              id: widget.employee?.id,
              name: _name.text.trim(),
              employeeCode: _code.text.trim(),
              designation: _designation.text.trim(),
              department: _department.text.trim(),
              email: _email.text.trim(),
              phone: _phone.text.trim(),
              region: _region.text.trim(),
              // The signed-in admin's *id*, not a rendered label. The write
              // side of `POST/PATCH /employees` resolves `reportingTo`
              // with `SELECT id FROM users WHERE id = ?`, so a display
              // string silently stores no manager at all. (The read side
              // hands back a label plus a separate `reportingToId`, an
              // asymmetry flagged for web-agent.)
              reportingTo: admin.id,
              joinedOn: _joinedOn,
              bloodGroup: _bloodGroup ?? '',
              address: _address.text.trim(),
              // Blank means "generate one" — the field is omitted from the
              // request entirely and the server answers with the credential.
              password: _isCreate && _password.text.trim().isNotEmpty
                  ? _password.text.trim()
                  : null,
            ),
          );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _saveError = e.message;
      });
      return;
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _saveError = 'Could not save. $e';
      });
      return;
    }

    if (!mounted) return;

    // The generated password is on this one response and nowhere else, so it
    // gets a modal the admin has to dismiss — not a snackbar that leaves before
    // it can be written down. Only then does the page pop.
    if (saved.temporaryPassword != null) {
      await showCredentialDialog(
        context,
        name: saved.employee.name,
        email: saved.employee.email,
        password: saved.temporaryPassword!,
      );
      if (!mounted) return;
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isCreate ? 'Employee added' : 'Employee updated'),
        ),
      );
    }

    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final bool ready = !_loading && _loadError == null;

    return Scaffold(
      appBar: AppBar(
        title: Text(_isCreate ? 'Add employee' : 'Edit employee'),
      ),
      // No save bar until the admin profile is in hand. Saving without it
      // would write an employee with no reporting line, which nothing later
      // in the console can repair from the app.
      bottomNavigationBar: !ready
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(Insets.lg),
                child: SizedBox(
                  height: Sizes.primaryActionHeight,
                  child: FilledButton(
                    onPressed: _busy ? null : _save,
                    child: _busy
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.2,
                              color: Colors.white,
                            ),
                          )
                        : Text(_isCreate ? 'Add employee' : 'Save changes'),
                  ),
                ),
              ),
            ),
      body: _loading
          ? const Padding(
              padding: EdgeInsets.all(Insets.lg),
              child: LoadingCards(count: 2),
            )
          : _loadError != null
              ? ErrorState(
                  onRetry: _load,
                  message: 'Could not load your admin profile, and the new '
                      'record needs it to set the reporting manager. Retry '
                      'when you are back online.',
                )
              : _formBody(context),
    );
  }

  Widget _formBody(BuildContext context) {
    return Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          if (_saveError != null) ...<Widget>[
            AlertBanner(
              icon: Icons.error_outline_rounded,
              title: 'Not saved',
              message: _saveError!,
              tone: AppColors.danger,
            ),
            const SizedBox(height: Insets.lg),
          ],
          AppCard(
            child: Column(
              children: <Widget>[
                _field(
                  controller: _name,
                  label: 'Full name',
                  icon: Icons.person_outline_rounded,
                  validator: (String? v) => (v ?? '').trim().length < 3
                      ? 'At least 3 characters'
                      : null,
                ),
                _field(
                  controller: _code,
                  label: 'Employee ID',
                  icon: Icons.badge_outlined,
                  helper: _codeHelper,
                  validator: (String? v) =>
                      RegExp(r'^EMP-\d{3,5}$').hasMatch((v ?? '').trim())
                          ? null
                          : 'Format: EMP-1042',
                ),
                _field(
                  controller: _designation,
                  label: 'Designation',
                  icon: Icons.work_outline_rounded,
                  validator: (String? v) =>
                      (v ?? '').trim().isEmpty ? 'Required' : null,
                ),
              ],
            ),
          ),
          const SizedBox(height: Insets.lg),
          AppCard(
            child: Column(
              children: <Widget>[
                _field(
                  controller: _department,
                  label: 'Department (optional)',
                  icon: Icons.apartment_rounded,
                  textCapitalization: TextCapitalization.words,
                ),
                _field(
                  controller: _region,
                  label: 'Region (optional)',
                  icon: Icons.map_outlined,
                  textCapitalization: TextCapitalization.words,
                ),
                SelectField<String>(
                  label: 'Blood group',
                  hint: 'Optional',
                  icon: Icons.water_drop_outlined,
                  clearable: true,
                  value: _bloodGroup,
                  options: const <String>[
                    'A+',
                    'A-',
                    'B+',
                    'B-',
                    'O+',
                    'O-',
                    'AB+',
                    'AB-',
                  ]
                      .map((String b) =>
                          SelectOption<String>(value: b, label: b))
                      .toList(),
                  onChanged: (String? v) => setState(() => _bloodGroup = v),
                ),
              ],
            ),
          ),
          const SizedBox(height: Insets.lg),
          AppCard(
            child: Column(
              children: <Widget>[
                _field(
                  controller: _email,
                  label: 'Email',
                  icon: Icons.mail_outline_rounded,
                  keyboardType: TextInputType.emailAddress,
                  validator: (String? v) =>
                      (v ?? '').contains('@') ? null : 'Invalid email',
                ),
                _field(
                  controller: _phone,
                  label: 'Phone',
                  icon: Icons.phone_outlined,
                  keyboardType: TextInputType.phone,
                  validator: (String? v) =>
                      (v ?? '').replaceAll(RegExp(r'\D'), '').length < 10
                          ? 'At least 10 digits'
                          : null,
                ),
                _field(
                  controller: _address,
                  label: 'Address',
                  icon: Icons.home_outlined,
                  maxLines: 2,
                ),
                // Not editable here: the write side takes a manager *id*
                // and the form has no manager picker, so the honest thing
                // is to show who it will be rather than imply a choice.
                if (_admin != null)
                  KeyValueRow(
                    label: 'Reports to',
                    value: '${_admin!.name} (${_admin!.role})',
                    dense: true,
                  ),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.event_outlined),
                  title: const Text('Joined on'),
                  subtitle: Text(Fmt.mediumDate(_joinedOn)),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () async {
                    final DateTime? picked = await showDatePicker(
                      context: context,
                      initialDate: _joinedOn,
                      firstDate: DateTime(2015),
                      lastDate: DateTime.now(),
                    );
                    if (picked != null) {
                      setState(() => _joinedOn = picked);
                    }
                  },
                ),
              ],
            ),
          ),
          // Create only. An existing employee's password cannot be read or
          // edited here — "Reset password" on their detail page issues a new
          // one, which is the only thing the API supports.
          if (_isCreate) ...<Widget>[
            const SizedBox(height: Insets.lg),
            AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  _field(
                    controller: _password,
                    label: 'Password (optional)',
                    icon: Icons.lock_outline_rounded,
                    helper: 'Leave blank and one will be generated for you',
                    validator: (String? v) {
                      final String value = (v ?? '').trim();
                      // Empty is the generate case, not a mistake. Anything
                      // typed has to clear the server's floor or the save is
                      // refused after the form has already been filled in.
                      if (value.isEmpty || value.length >= 8) return null;
                      return 'At least 8 characters, or leave blank';
                    },
                  ),
                  Text(
                    'The employee signs in with their email and this password. '
                    'A generated one is shown once, right after you save.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    String? Function(String?)? validator,
    TextInputType? keyboardType,
    TextCapitalization textCapitalization = TextCapitalization.none,
    int maxLines = 1,
    String? helper,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: TextFormField(
        controller: controller,
        validator: validator,
        keyboardType: keyboardType,
        textCapitalization: textCapitalization,
        maxLines: maxLines,
        decoration: InputDecoration(
          labelText: label,
          helperText: helper,
          prefixIcon: Icon(icon),
        ),
      ),
    );
  }
}
