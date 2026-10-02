import 'product.dart';

/// Employee-submitted visit report. Independent record — one company can hold
/// any number of these.
///
/// Company and branch are **free text**, not foreign keys: sellers do not work
/// a fixed route, so the shop they walk into may not exist in any master list.
/// [companyId] / [branchId] are kept as an *optional* link, set only when the
/// report was written against a detected visit to a known branch — that is what
/// still ties a report to a pin on the admin map. Display always uses
/// [companyName] / [branchName].

enum ReportStatus { draft, queued, uploading, submitted, failed, reviewed }

extension ReportStatusX on ReportStatus {
  String get label => switch (this) {
        ReportStatus.draft => 'Draft',
        ReportStatus.queued => 'Queued',
        ReportStatus.uploading => 'Uploading',
        ReportStatus.submitted => 'Submitted',
        ReportStatus.failed => 'Failed',
        ReportStatus.reviewed => 'Reviewed',
      };

  bool get isPending => switch (this) {
        ReportStatus.draft || ReportStatus.queued || ReportStatus.uploading => true,
        _ => false,
      };
}

class VisitReport {
  const VisitReport({
    required this.id,
    required this.companyName,
    required this.branchName,
    required this.visitId,
    required this.sessionId,
    required this.title,
    required this.body,
    required this.imageCount,
    required this.submittedAt,
    required this.latitude,
    required this.longitude,
    required this.status,
    this.companyId,
    this.branchId,
    this.dealValue,
    this.followUpOn,
    this.sales = const <ProductSaleLine>[],
    this.paymentReceived,
  });

  final String id;

  /// Typed by the seller. Never looked up.
  final String companyName;
  final String? branchName;

  /// Set only when the report was filed against a known company/branch, which
  /// is how it links to a visit pin. Null for a walk-in.
  final String? companyId;
  final String? branchId;

  /// Null when the report was written outside a detected visit.
  final String? visitId;
  final String sessionId;
  final String title;
  final String body;
  final int imageCount;
  final DateTime submittedAt;
  final double latitude;
  final double longitude;
  final ReportStatus status;
  final String? dealValue;
  final DateTime? followUpOn;

  /// EV units logged against this visit. Empty when nothing was sold.
  final List<ProductSaleLine> sales;

  /// Amount actually collected on the visit, as typed. Null when nothing was
  /// received.
  final String? paymentReceived;

  int get unitsSold =>
      sales.fold<int>(0, (int sum, ProductSaleLine l) => sum + l.units);

  bool get hasSale => sales.isNotEmpty;

  String get preview {
    final String flat = body.replaceAll(RegExp(r'\s+'), ' ').trim();
    return flat.length <= 120 ? flat : '${flat.substring(0, 120)}…';
  }
}
