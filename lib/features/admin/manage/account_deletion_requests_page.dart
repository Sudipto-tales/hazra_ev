import 'package:flutter/material.dart';
import '../../../data/models/account_deletion_request.dart';
import '../../../state/app_scope.dart';
import '../../profile/account_deletion_page.dart';

class AccountDeletionRequestsPage extends StatefulWidget {
  const AccountDeletionRequestsPage({super.key});
  @override
  State<AccountDeletionRequestsPage> createState() =>
      _AccountDeletionRequestsPageState();
}

class _AccountDeletionRequestsPageState
    extends State<AccountDeletionRequestsPage> {
  List<AccountDeletionRequest>? _requests;
  String? _error;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final result =
          await AppScope.of(context).adminRepository.accountDeletionRequests();
      if (mounted) {
        setState(() {
          _requests = result;
          _error = null;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Unable to load deletion requests.');
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Account deletion requests')),
      body: _error != null
          ? Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text(_error!),
              TextButton(onPressed: _load, child: const Text('Retry')),
            ]))
          : _requests == null
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(padding: const EdgeInsets.all(16), children: [
                    Text(
                        '${_requests!.where((r) => r.isPending).length} pending requests',
                        style: Theme.of(context).textTheme.titleMedium),
                    if (_requests!.isEmpty)
                      const Padding(
                          padding: EdgeInsets.only(top: 40),
                          child: Text('No account deletion requests.')),
                    for (final request in _requests!)
                      Card(
                          child: ListTile(
                              title: Text(request.employeeName),
                              isThreeLine: true,
                              subtitle: Text(
                                  '${request.employee['employee_code'] ?? request.employeeId}\n${request.isPending ? 'Pending · due' : 'Deleted · requested'} ${deletionDateLabel(request.isPending ? request.deleteAfter : request.requestedAt)}'),
                              trailing: const Icon(Icons.chevron_right),
                              onTap: () async {
                                await Navigator.of(context).push(
                                    MaterialPageRoute<void>(
                                        builder: (_) =>
                                            AccountDeletionReviewPage(
                                                request: request)));
                                if (mounted) _load();
                              })),
                  ])));
}

class AccountDeletionReviewPage extends StatefulWidget {
  const AccountDeletionReviewPage({super.key, required this.request});
  final AccountDeletionRequest request;
  @override
  State<AccountDeletionReviewPage> createState() =>
      _AccountDeletionReviewPageState();
}

class _AccountDeletionReviewPageState extends State<AccountDeletionReviewPage> {
  final TextEditingController _adminNote = TextEditingController();
  final TextEditingController _confirmation = TextEditingController();
  late AccountDeletionRequest _request = widget.request;
  bool _busy = false;
  String? _error;
  @override
  void dispose() {
    _confirmation.dispose();
    _adminNote.dispose();
    super.dispose();
  }

  Future<void> _approve() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await AppScope.of(context)
          .adminRepository
          .approveAccountDeletion(_request.id,
              confirmation: _confirmation.text, adminNote: _adminNote.text);
      if (mounted) setState(() => _request = result);
    } catch (_) {
      if (mounted) {
        setState(() => _error =
            'Deletion could not be confirmed. Refresh the request or try again.');
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Review deletion request')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Text(_request.employeeName,
            style: Theme.of(context).textTheme.headlineSmall),
        SelectableText('Employee ID: ${_request.employeeId}'),
        for (final key in [
          'employee_code',
          'email',
          'phone',
          'designation',
          'department'
        ])
          Text('${key.replaceAll('_', ' ')}: ${_request.employee[key] ?? '—'}'),
        const SizedBox(height: 16),
        AccountDeletionStatusCard(request: _request, forEmployee: false),
        Text(
            'Reason: ${_request.reason.isEmpty ? 'Not provided' : _request.reason}'),
        if (!_request.isPending) ...[
          Text('Deleted: ${deletionDateLabel(_request.deletedAt!)}'),
          Text('Source: ${_request.deletionSource ?? 'admin'}'),
          Text(
              'Admin note: ${_request.adminNote.isEmpty ? 'Not provided' : _request.adminNote}'),
        ],
        if (_request.isPending) ...[
          const SizedBox(height: 24),
          const Text(
              'Approving deletes access immediately, changes the current employee name to Unknown and marks the account Deleted. Work history is preserved. This cannot be undone.'),
          const SizedBox(height: 16),
          TextField(
              controller: _adminNote,
              enabled: !_busy,
              maxLines: 3,
              maxLength: 500,
              decoration: const InputDecoration(
                  labelText: 'Deletion note (optional)',
                  helperText: 'Saved for future verification',
                  border: OutlineInputBorder())),
          const SizedBox(height: 16),
          TextField(
              controller: _confirmation,
              enabled: !_busy,
              autocorrect: false,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(
                  labelText: 'Type delete account to confirm',
                  border: OutlineInputBorder())),
          const SizedBox(height: 16),
          FilledButton(
              style: FilledButton.styleFrom(
                  backgroundColor: Theme.of(context).colorScheme.error),
              onPressed: !_busy && _confirmation.text == 'delete account'
                  ? _approve
                  : null,
              child: Text(_busy ? 'Deleting…' : 'Delete account')),
        ],
        if (_error != null)
          Text(_error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error)),
      ]));
}
