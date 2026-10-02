import 'package:flutter/material.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';

String deletionDateLabel(DateTime value) {
  final DateTime d = value.toLocal();
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec'
  ];
  return '${d.day} ${months[d.month - 1]} ${d.year}, ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')} (local time)';
}

class AccountDeletionPage extends StatefulWidget {
  const AccountDeletionPage({super.key});
  @override
  State<AccountDeletionPage> createState() => _AccountDeletionPageState();
}

class _AccountDeletionPageState extends State<AccountDeletionPage> {
  final TextEditingController _reason = TextEditingController();
  Employee? _employee;
  AccountDeletionRequest? _request;
  bool _loading = true, _submitting = false, _confirmed = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = AppScope.of(context).repository;
      final Employee employee = await repo.profile();
      final AccountDeletionRequest? request =
          await repo.accountDeletionRequest();
      if (mounted) {
        setState(() {
          _employee = employee;
          _request = request;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Unable to load your deletion request. Please try again.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await AppScope.of(context)
          .repository
          .submitAccountDeletion(reason: _reason.text.trim());
      if (mounted) setState(() => _request = result);
    } catch (_) {
      if (mounted) {
        setState(() => _error =
            'Unable to submit the request. Check your connection and try again. Retrying will not create a duplicate request.');
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Delete account')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(padding: const EdgeInsets.all(20), children: [
              if (_error != null) ...[
                Text(_error!,
                    style:
                        TextStyle(color: Theme.of(context).colorScheme.error)),
                if (_employee == null)
                  TextButton(onPressed: _load, child: const Text('Retry')),
                const SizedBox(height: 16),
              ],
              if (_request != null)
                AccountDeletionStatusCard(request: _request!),
              if (_request == null && _employee != null) ...[
                Text('Request account deletion',
                    style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 12),
                const Text(
                    'Your account will be deleted within 30 days of submitting this request. An admin may approve it sooner. You can keep using the app until deletion.'),
                const SizedBox(height: 16),
                Card(
                    child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(_employee!.name),
                              Text('Employee ID: ${_employee!.employeeCode}'),
                              Text(_employee!.email),
                              Text(_employee!.phone),
                            ]))),
                const SizedBox(height: 16),
                TextField(
                    controller: _reason,
                    maxLength: 2000,
                    maxLines: 4,
                    enabled: !_submitting,
                    decoration: const InputDecoration(
                        labelText: 'Reason (optional)',
                        border: OutlineInputBorder())),
                CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _confirmed,
                    onChanged: _submitting
                        ? null
                        : (v) => setState(() => _confirmed = v ?? false),
                    title: const Text(
                        'I understand that deletion is permanent. I will lose access; my name will become Unknown. Work history and a restricted deletion record will be retained.'),
                    controlAffinity: ListTileControlAffinity.leading),
                FilledButton(
                    onPressed: _confirmed && !_submitting ? _submit : null,
                    child: Text(_submitting
                        ? 'Submitting…'
                        : 'Submit deletion request')),
              ],
            ]));
}

class AccountDeletionStatusCard extends StatelessWidget {
  const AccountDeletionStatusCard(
      {super.key, required this.request, this.forEmployee = true});
  final AccountDeletionRequest request;
  final bool forEmployee;
  @override
  Widget build(BuildContext context) => Card(
      child: Padding(
          padding: const EdgeInsets.all(20),
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(
                request.isPending ? Icons.schedule : Icons.person_off_outlined),
            const SizedBox(height: 12),
            Text(
                request.isPending
                    ? 'Account deletion requested'
                    : 'Account deleted',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            Text(request.isPending
                ? forEmployee
                    ? 'Your account will be deleted within 30 days of your request. An admin may approve it sooner. You can continue using the app until your account is deleted. After deletion, you will no longer be able to sign in.'
                    : 'This employee’s account will be deleted within 30 days of the request. Admin approval deletes it sooner. The employee retains access until deletion.'
                : 'This account has been deleted and can no longer sign in.'),
            const Divider(height: 32),
            SelectableText('Ticket: ${request.ticketNumber}'),
            Text('Requested: ${deletionDateLabel(request.requestedAt)}'),
            Text(
                'Deletion deadline: ${deletionDateLabel(request.deleteAfter)}'),
          ])));
}
