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
    this.images,
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

  /// The attachments, when they were asked for (`?include=images`).
  ///
  /// **Null means "not fetched", empty means "none".** The distinction is the
  /// whole point: list responses carry [imageCount] only, so a screen that
  /// renders from a list row must not conclude from a missing array that the
  /// report has no photos — which is exactly the mistake that left the detail
  /// page showing empty slots. Use [hasFetchedImages] to tell them apart.
  final List<ReportImage>? images;

  /// True when the server was asked for the attachment array, whatever it
  /// came back with.
  bool get hasFetchedImages => images != null;

  int get unitsSold =>
      sales.fold<int>(0, (int sum, ProductSaleLine l) => sum + l.units);

  bool get hasSale => sales.isNotEmpty;

  /// How many attachment slots to draw. Prefers the fetched array over
  /// [imageCount] so a detail screen never renders a count the array cannot
  /// fill (they can disagree for a moment if an upload lands between reads).
  int get visibleImageCount => images?.length ?? imageCount;

  String get preview {
    final String flat = body.replaceAll(RegExp(r'\s+'), ' ').trim();
    return flat.length <= 120 ? flat : '${flat.substring(0, 120)}…';
  }
}

/// One stored attachment on a report.
///
/// Mirrors `Present::image`. Only ever constructed from a server payload, so
/// [uploadState] is always `uploaded` — queued and failed are device-side
/// states that belong to the pick-and-upload flow, not to a stored row.
class ReportImage {
  const ReportImage({
    required this.id,
    required this.reportId,
    required this.url,
    this.thumbnailUrl,
    this.width,
    this.height,
    this.bytes,
    this.capturedAt,
    this.latitude,
    this.longitude,
  });

  final String id;
  final String reportId;

  /// Path relative to the PHP document root (`storage/uploads/...`), so it
  /// resolves against the API **origin**, not the `/api/v1` prefix. Resolved
  /// by `ApiConfig.originUrl` at the render site.
  final String url;

  /// Server-side thumbnail where one exists. Null today — nothing generates
  /// them yet — so the grid loads [url] and lets the image cache downscale.
  final String? thumbnailUrl;

  /// Pixel dimensions, when the upload could read them. Used to reserve the
  /// right aspect ratio before the bytes arrive, so a grid does not reflow.
  final int? width;
  final int? height;
  final int? bytes;

  final DateTime? capturedAt;
  final double? latitude;
  final double? longitude;

  double? get aspectRatio {
    final int? w = width;
    final int? h = height;
    if (w == null || h == null || w <= 0 || h <= 0) return null;
    return w / h;
  }
}
