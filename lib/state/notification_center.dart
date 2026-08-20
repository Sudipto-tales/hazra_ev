import 'package:flutter/foundation.dart';

import '../data/models/models.dart';

/// In-app notification feed.
///
/// A [ChangeNotifier] rather than a plain repository read, because the whole
/// point is that the employee's bell reacts the moment the admin lists a
/// product — with both shells running off one instance there is nothing to poll
/// and nothing to refresh. `EmployeeRepository.notifications()` still exists so
/// the contract stays HTTP-shaped; this is what it reads.
///
/// In-memory for the session, exactly like submitted reports and report
/// reviews.
class NotificationCenter extends ChangeNotifier {
  NotificationCenter({List<AppNotification>? seed})
      : _items = <AppNotification>[...?seed];

  final List<AppNotification> _items;

  /// Newest first.
  List<AppNotification> get items => List<AppNotification>.unmodifiable(_items);

  int get unreadCount => _items.where((AppNotification n) => !n.read).length;

  bool get hasUnread => unreadCount > 0;

  void push(AppNotification notification) {
    _items.insert(0, notification);
    notifyListeners();
  }

  void markRead(String id) {
    final int i = _items.indexWhere((AppNotification n) => n.id == id);
    if (i < 0 || _items[i].read) return;
    _items[i] = _items[i].copyWith(read: true);
    notifyListeners();
  }

  void markAllRead() {
    if (!hasUnread) return;
    for (int i = 0; i < _items.length; i++) {
      if (!_items[i].read) _items[i] = _items[i].copyWith(read: true);
    }
    notifyListeners();
  }

  void clear() {
    if (_items.isEmpty) return;
    _items.clear();
    notifyListeners();
  }
}
