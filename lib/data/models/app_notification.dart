/// One entry in the employee's in-app feed.
///
/// Today the only producer is the admin listing or updating a product, but the
/// kinds are open so a review verdict or a broadcast can land here later
/// without a model change.
library;

enum NotificationKind { newProduct, productUpdated, reportReviewed, announcement }

extension NotificationKindX on NotificationKind {
  String get label => switch (this) {
        NotificationKind.newProduct => 'New product',
        NotificationKind.productUpdated => 'Product updated',
        NotificationKind.reportReviewed => 'Report reviewed',
        NotificationKind.announcement => 'Announcement',
      };
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.kind,
    required this.title,
    required this.message,
    required this.createdAt,
    this.read = false,
    this.productId,
    this.reportId,
  });

  final String id;
  final NotificationKind kind;
  final String title;
  final String message;
  final DateTime createdAt;
  final bool read;

  /// Set on catalogue notifications so tapping one can open that product.
  final String? productId;
  final String? reportId;

  AppNotification copyWith({bool? read}) => AppNotification(
        id: id,
        kind: kind,
        title: title,
        message: message,
        createdAt: createdAt,
        read: read ?? this.read,
        productId: productId,
        reportId: reportId,
      );
}
