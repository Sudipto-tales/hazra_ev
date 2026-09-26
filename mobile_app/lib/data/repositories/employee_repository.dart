import '../models/models.dart';

/// Contract the UI talks to. Swap the implementation (mock → HTTP) without
/// touching a single widget.
///
/// Method names deliberately track the planned endpoints:
///   home()            → GET /api/employee/home
///   activity()        → GET /api/employee/activity/today
///   reports()         → GET /api/employee/reports/today
///   attendance()      → GET /api/employee/calendar
///   statistics()      → GET /api/employee/statistics
///   profile()         → GET /api/employee/profile
///   products()        → GET /api/catalog/products?category=
///   productById()     → GET /api/catalog/products/{id}
///   notifications()   → GET /api/employee/notifications
///   uploadReportImages() → POST  /reports/{id}/images  (multipart)
///   preferences()        → GET   /me/preferences
///   savePreferences()    → PATCH /me/preferences       (partial)
abstract class EmployeeRepository {
  Future<Employee> profile();

  /// `PATCH /me`. Self-service edits, and the server is strict about which:
  /// `avatarUrl` and `phone` are the only fields it accepts, and it rejects a
  /// body containing anything else. Name, designation, department and address
  /// are changed by an admin through `PATCH /employees/{id}`, not from here.
  ///
  /// Passing neither argument is a programming error, not a no-op request —
  /// the server answers an empty patch with a validation failure.
  Future<Employee> updateProfile({String? phone, String? avatarUrl});

  Future<HomeSnapshot> home();

  Future<List<ActivityEvent>> activity({DateTime? date});

  /// Reports for [date]; omit [date] for the full history (newest first).
  Future<List<VisitReport>> reports({DateTime? date});

  Future<VisitReport> reportById(String id);

  Future<List<Attendance>> attendance({required DateTime month});

  Future<Attendance?> attendanceForDate(DateTime date);

  Future<PeriodStatistics> statistics({
    required StatsRange range,
    DateTime? from,
    DateTime? to,
  });

  Future<List<Company>> companies();

  /// EV catalogue for the sale card on the report form. Omit [category] for the
  /// whole catalogue. Only products the admin currently has listed come back.
  Future<List<Product>> products({ProductCategory? category, String? query});

  Future<Product> productById(String id);

  /// Feed behind the bell on Home — newest first. The live badge reads
  /// `NotificationCenter` directly; this is the same data over the contract.
  Future<List<AppNotification>> notifications();

  Future<void> markNotificationRead(String id);

  Future<void> markAllNotificationsRead();

  /// Returns the stored record. In the live app this queues offline and retries.
  ///
  /// Text only — the draft's [ReportDraft.images] are **not** part of this
  /// request. Call [uploadReportImages] with the returned id afterwards.
  Future<VisitReport> submitReport(ReportDraft draft);

  /// `POST /reports/{id}/images` — multipart, one part per picture.
  ///
  /// Deliberately a second call rather than part of [submitReport], because
  /// that is how the server models it ("a report can submit while its images
  /// are still queued"). The consequence the UI must honour: by the time this
  /// method runs the report already exists, so a failure here loses the
  /// pictures and never the report.
  ///
  /// Returns the images the server actually stored, in the order it stored
  /// them. **Fewer back than sent is a partial upload, not a success** — the
  /// server writes each part as it validates it and stops at the first one it
  /// rejects, so what comes back is the prefix that made it.
  Future<List<ReportImage>> uploadReportImages(
    String reportId,
    List<ReportAttachment> images,
  );

  /// Ends the day for good: stores the employee's declaration and locks the
  /// day server-side. Deliberately **not** queued offline — the lock lives on
  /// the server, so a device that closes the day while disconnected would be
  /// the only thing that believes it. Callers must be online.
  ///
  /// Idempotent on `draft.clientId`; a retry returns the existing record.
  Future<DayCloseout> submitDayCloseout(DayCloseoutDraft draft);

  /// Changes the signed-in account's own password.
  ///
  /// Knowing [current] is the authorisation — an access token alone must not
  /// be enough to lock the owner out. Every *other* device is signed out;
  /// this one is not, because the caller's refresh token is passed through.
  ///
  /// Throws [ApiException] with a 403 when [current] is wrong.
  Future<void> changePassword({
    required String current,
    required String next,
  });

  /// `GET /me/preferences`. The nine values behind the Settings screen.
  Future<DevicePreferences> preferences();

  /// `PATCH /me/preferences`. Partial by contract — pass only what changed;
  /// every omitted argument is left alone server-side.
  ///
  /// **Adopt the return value, do not assume the write took.** The last three
  /// flags are clamped against the admin `TrackingConfig` ceiling: a device
  /// preference may only make collection *less* aggressive, never more, so
  /// asking for `highAccuracyMode: true` under a low-accuracy policy comes
  /// back false.
  Future<DevicePreferences> savePreferences({
    String? themeMode,
    String? language,
    bool? notificationsEnabled,
    bool? reportReminders,
    bool? sessionReminders,
    bool? systemNotifications,
    bool? highAccuracyMode,
    bool? syncOnMobileData,
    bool? batterySaver,
  });
}

/// What the create-report form collects before it becomes a [VisitReport].
///
/// Company and branch are typed by the seller — visits are not fixed, so there
/// is no id to pick. [companyId] / [branchId] ride along only when the form was
/// opened from, or linked to, a detected visit.
class ReportDraft {
  const ReportDraft({
    required this.companyName,
    required this.branchName,
    required this.title,
    required this.body,
    this.images = const <ReportAttachment>[],
    this.companyId,
    this.branchId,
    this.visitId,
    this.dealValue,
    this.followUpOn,
    this.sales = const <ProductSaleLine>[],
    this.paymentReceived,
  });

  final String companyName;
  final String? branchName;
  final String? companyId;
  final String? branchId;
  final String title;
  final String body;

  /// Pictures picked on this device, still on local storage. They travel in
  /// their own request — see [EmployeeRepository.uploadReportImages] — and are
  /// carried on the draft only so the form keeps its state in one place.
  final List<ReportAttachment> images;
  final String? visitId;
  final String? dealValue;
  final DateTime? followUpOn;

  /// One line per product+colour the seller logged.
  final List<ProductSaleLine> sales;
  final String? paymentReceived;
}

/// A picture chosen on the device, on its way to `POST /reports/{id}/images`.
///
/// Declared next to [ReportDraft] rather than in `lib/data/models/` on purpose:
/// it is not part of any server payload. It exists only between the picker and
/// the upload. Its stored counterpart is [ReportImage].
class ReportAttachment {
  const ReportAttachment({
    required this.path,
    required this.name,
    required this.byteSize,
  });

  /// Absolute path on this device. The multipart body streams from here, so
  /// the file has to still exist when the upload runs — the camera cache is
  /// not ours and the OS is free to reclaim it.
  final String path;

  /// Original file name. Also what gives the upload its extension, which is
  /// what the server derives the stored filename from.
  final String name;

  final int byteSize;

  /// `ReportsController::MAX_IMAGE_BYTES`. Mirrored here so an oversized frame
  /// is refused in the picker rather than coming back as a 422 after the
  /// seller has already tapped Submit.
  static const int maxBytes = 8 * 1024 * 1024;

  /// `ReportsController::IMAGE_TYPES`. The server decides for itself by
  /// sniffing the content with `getimagesize`; an extension is all the client
  /// can check cheaply, so this is a pre-flight filter and not a guarantee.
  static const Set<String> allowedExtensions = <String>{
    'jpg',
    'jpeg',
    'png',
    'webp',
  };

  String get extension {
    final int dot = name.lastIndexOf('.');
    return dot < 0 ? '' : name.substring(dot + 1).toLowerCase();
  }

  bool get isTooLarge => byteSize > maxBytes;

  bool get isSupportedType => allowedExtensions.contains(extension);

  /// Null when the file is acceptable; otherwise the reason, phrased for the
  /// seller rather than for a log.
  String? get rejection {
    if (!isSupportedType) {
      return '$name is not a JPEG, PNG or WebP';
    }
    if (isTooLarge) {
      return '$name is larger than 8 MB';
    }
    return null;
  }

  String get sizeLabel => byteSize >= 1024 * 1024
      ? '${(byteSize / (1024 * 1024)).toStringAsFixed(1)} MB'
      : '${(byteSize / 1024).round()} KB';
}

/// One image the server has stored — the shape `Present::image` returns from
/// `POST /reports/{id}/images`.
///
/// Only the upload response ever produces one of these. `GET /reports` and
/// `GET /reports/{id}` carry `imageCount` and nothing else: no urls, no
/// `include=images`. So a report read back from the server cannot be shown
/// with its pictures, and nothing in the app should pretend otherwise.
class ReportImage {
  const ReportImage({
    required this.id,
    required this.reportId,
    required this.url,
    this.thumbnailUrl,
    this.width,
    this.height,
    this.byteSize,
  });

  final String id;
  final String reportId;

  /// Server-relative, e.g. `storage/uploads/reports/{reportId}/{uuid}.jpg`.
  /// Deliberately not resolved against a host here — nothing renders it yet,
  /// and guessing the public origin is how you ship a broken image.
  final String url;
  final String? thumbnailUrl;
  final int? width;
  final int? height;
  final int? byteSize;
}

/// `GET /me/preferences` — the nine fields `SettingsController` owns, exactly.
///
/// [themeMode] is the wire string (`system` / `light` / `dark`), not a Flutter
/// `ThemeMode`: the data layer does not import the widget layer, and mapping an
/// unknown value belongs to the controller that has to survive one.
class DevicePreferences {
  const DevicePreferences({
    required this.themeMode,
    required this.language,
    required this.notificationsEnabled,
    required this.reportReminders,
    required this.sessionReminders,
    required this.systemNotifications,
    required this.highAccuracyMode,
    required this.syncOnMobileData,
    required this.batterySaver,
  });

  /// The server's own defaults, from `Present::preferences`. Used by the mock
  /// and as the starting point before a first sync.
  static const DevicePreferences defaults = DevicePreferences(
    themeMode: 'system',
    language: 'English',
    notificationsEnabled: true,
    reportReminders: true,
    sessionReminders: true,
    systemNotifications: true,
    highAccuracyMode: true,
    syncOnMobileData: true,
    batterySaver: false,
  );

  final String themeMode;
  final String language;
  final bool notificationsEnabled;
  final bool reportReminders;
  final bool sessionReminders;
  final bool systemNotifications;
  final bool highAccuracyMode;
  final bool syncOnMobileData;
  final bool batterySaver;

  DevicePreferences copyWith({
    String? themeMode,
    String? language,
    bool? notificationsEnabled,
    bool? reportReminders,
    bool? sessionReminders,
    bool? systemNotifications,
    bool? highAccuracyMode,
    bool? syncOnMobileData,
    bool? batterySaver,
  }) {
    return DevicePreferences(
      themeMode: themeMode ?? this.themeMode,
      language: language ?? this.language,
      notificationsEnabled: notificationsEnabled ?? this.notificationsEnabled,
      reportReminders: reportReminders ?? this.reportReminders,
      sessionReminders: sessionReminders ?? this.sessionReminders,
      systemNotifications: systemNotifications ?? this.systemNotifications,
      highAccuracyMode: highAccuracyMode ?? this.highAccuracyMode,
      syncOnMobileData: syncOnMobileData ?? this.syncOnMobileData,
      batterySaver: batterySaver ?? this.batterySaver,
    );
  }
}
