import 'package:flutter/foundation.dart';

import '../models/models.dart';

/// Whether today is closed, and who closed it.
///
/// **One instance is shared by both mock repositories** — the employee closes
/// the day through `submitDayCloseout`, the admin reopens it through
/// `reopenDay`, and each side sees the other's move with no plumbing in
/// between. Same arrangement as [ProductStore], for the same reason.
///
/// In the live app this state lives on the server, which is the whole point of
/// the lock: a day closed on one device has to be closed everywhere.
class DayLockStore extends ChangeNotifier {
  DayCloseout? _closeout;
  final List<DayReopen> _reopens = <DayReopen>[];

  DayCloseout? get closeout => _closeout;

  List<DayReopen> get reopens => List<DayReopen>.unmodifiable(_reopens);

  DayState get state =>
      _closeout == null ? DayState.open : DayState.closedByEmployee;

  bool get isLocked => state.isLocked;

  /// Idempotent, like the endpoint: closing an already-closed day returns the
  /// declaration that is already on record rather than writing a second one.
  DayCloseout close(DayCloseout closeout) {
    final DayCloseout? existing = _closeout;
    if (existing != null) return existing;
    _closeout = closeout;
    notifyListeners();
    return closeout;
  }

  /// The admin's undo. The submitted declaration is deliberately kept in
  /// [reopens] — it is history, not a mistake, and the numbers on it are the
  /// only record of what the employee actually claimed.
  void reopen({required String reason, required String by}) {
    final DayCloseout? closed = _closeout;
    if (closed == null) return;
    _reopens.add(DayReopen(closeout: closed, reason: reason, by: by, at: DateTime.now()));
    _closeout = null;
    notifyListeners();
  }
}

/// One admin reopen, kept so the day's numbers can always be explained.
class DayReopen {
  const DayReopen({
    required this.closeout,
    required this.reason,
    required this.by,
    required this.at,
  });

  final DayCloseout closeout;
  final String reason;
  final String by;
  final DateTime at;
}
