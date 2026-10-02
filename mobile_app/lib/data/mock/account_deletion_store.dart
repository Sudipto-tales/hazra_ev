import '../models/account_deletion_request.dart';
import '../models/employee.dart';

/// Shared by the employee and admin demo repositories; live requests use PHP.
class AccountDeletionStore {
  final Map<String, AccountDeletionRequest> requests = {};
  AccountDeletionRequest submit(Employee employee, String reason) {
    return requests.putIfAbsent(employee.id, () {
      final DateTime now = DateTime.now().toUtc();
      return AccountDeletionRequest(
          id: 'demo-${employee.id}',
          ticketNumber: 'DEL-DEMO-${employee.employeeCode}',
          employeeId: employee.id,
          employee: {
            'name': employee.name,
            'email': employee.email,
            'phone': employee.phone,
            'employee_code': employee.employeeCode,
            'designation': employee.designation,
            'department': employee.department
          },
          reason: reason,
          status: 'pending',
          requestedAt: now,
          deleteAfter: now.add(const Duration(days: 30)));
    });
  }

  AccountDeletionRequest approve(String id, String confirmation,
      {String adminNote = ''}) {
    if (confirmation != 'delete account') {
      throw ArgumentError('Type delete account exactly');
    }
    final AccountDeletionRequest old =
        requests.values.firstWhere((r) => r.id == id);
    final AccountDeletionRequest result = AccountDeletionRequest(
        id: old.id,
        ticketNumber: old.ticketNumber,
        employeeId: old.employeeId,
        employee: old.employee,
        reason: old.reason,
        status: 'deleted',
        requestedAt: old.requestedAt,
        deleteAfter: old.deleteAfter,
        deletedAt: old.deletedAt ?? DateTime.now().toUtc(),
        deletionSource: old.deletionSource ?? 'admin',
        adminNote: old.isPending ? adminNote.trim() : old.adminNote);
    requests[old.employeeId] = result;
    return result;
  }
}
