import 'package:flutter/material.dart';

import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/mock/admin_mock_data.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/select_field.dart';

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
  late final TextEditingController _code = TextEditingController(
    text: widget.employee?.employeeCode ?? _suggestedCode(),
  );
  late final TextEditingController _designation =
      TextEditingController(text: widget.employee?.designation ?? '');
  late final TextEditingController _email =
      TextEditingController(text: widget.employee?.email ?? '');
  late final TextEditingController _phone =
      TextEditingController(text: widget.employee?.phone ?? '');
  late final TextEditingController _address =
      TextEditingController(text: widget.employee?.address ?? '');

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

  bool get _isCreate => widget.employee == null;

  static String _suggestedCode() {
    final int n = 1050 + AdminMockData.employees.length;
    return 'EMP-$n';
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
    super.dispose();
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;

    setState(() => _busy = true);
    await AppScope.of(context).adminRepository.saveEmployee(
          EmployeeDraft(
            id: widget.employee?.id,
            name: _name.text.trim(),
            employeeCode: _code.text.trim(),
            designation: _designation.text.trim(),
            department: _department.text.trim(),
            email: _email.text.trim(),
            phone: _phone.text.trim(),
            region: _region.text.trim(),
            reportingTo: '${AdminMockData.admin.name} '
                '(${AdminMockData.admin.role})',
            joinedOn: _joinedOn,
            bloodGroup: _bloodGroup ?? '',
            address: _address.text.trim(),
          ),
        );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(_isCreate ? 'Employee added' : 'Employee updated'),
      ),
    );
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_isCreate ? 'Add employee' : 'Edit employee'),
      ),
      bottomNavigationBar: SafeArea(
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
    TextInputType? keyboardType,
    TextCapitalization textCapitalization = TextCapitalization.none,
    int maxLines = 1,
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
          prefixIcon: Icon(icon),
        ),
      ),
    );
  }
}
