class AccountDeletionRequest {
  const AccountDeletionRequest(
      {required this.id,
      required this.ticketNumber,
      required this.employeeId,
      required this.employee,
      required this.reason,
      required this.status,
      required this.requestedAt,
      required this.deleteAfter,
      this.deletedAt,
      this.deletionSource,
      this.adminNote = ''});

  final String id, ticketNumber, employeeId, reason, status;
  final Map<String, dynamic> employee;
  final DateTime requestedAt, deleteAfter;
  final DateTime? deletedAt;
  final String? deletionSource;
  final String adminNote;
  bool get isPending => status == 'pending';
  String get employeeName => employee['name'] as String? ?? 'Unknown';
  factory AccountDeletionRequest.fromJson(Map<String, dynamic> json) =>
      AccountDeletionRequest(
          id: json['id'] as String,
          ticketNumber: json['ticketNumber'] as String,
          employeeId: json['employeeId'] as String,
          employee: Map<String, dynamic>.from(json['employee'] as Map),
          reason: json['reason'] as String? ?? '',
          status: json['status'] as String,
          requestedAt: DateTime.parse(json['requestedAt'] as String),
          deleteAfter: DateTime.parse(json['deleteAfter'] as String),
          deletedAt: json['deletedAt'] == null
              ? null
              : DateTime.parse(json['deletedAt'] as String),
          deletionSource: json['deletionSource'] as String?,
          adminNote: json['adminNote'] as String? ?? '');
}
