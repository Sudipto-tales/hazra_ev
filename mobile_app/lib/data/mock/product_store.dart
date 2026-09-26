import 'package:flutter/foundation.dart';

import '../models/models.dart';
import 'product_catalog.dart';

/// The live EV catalogue.
///
/// [ProductCatalog] is the immutable seed; this is the mutable thing the app
/// actually reads. **One instance is shared by both repositories** — the admin
/// writes to it through `saveProduct`, the employee report form reads it
/// through `products()`, so a listing appears on the other side with no
/// plumbing in between.
///
/// Not a singleton: `main.dart` constructs it and passes it to both mocks, and
/// a test can build a fresh one. In-memory for the session, like every other
/// write in this build.
class ProductStore extends ChangeNotifier {
  ProductStore({List<Product>? seed})
      : _items = <Product>[...(seed ?? ProductCatalog.all)];

  final List<Product> _items;

  /// Delisted ids. Kept rather than deleted so reports that already reference
  /// a product still read correctly.
  final Set<String> _inactive = <String>{};

  int _seq = 0;

  /// Everything, listed or delisted — the admin's view.
  List<Product> get all => List<Product>.unmodifiable(_items);

  /// What a seller may log against a report.
  List<Product> get active => _items
      .where((Product p) => !_inactive.contains(p.id))
      .toList(growable: false);

  bool isActive(String id) => !_inactive.contains(id);

  Product? byId(String id) {
    for (final Product p in _items) {
      if (p.id == id) return p;
    }
    return null;
  }

  List<Product> byCategory(ProductCategory category, {bool activeOnly = true}) =>
      (activeOnly ? active : _items)
          .where((Product p) => p.category == category)
          .toList(growable: false);

  /// Creates when [draft] has no id, otherwise replaces in place so the
  /// catalogue keeps its order.
  Product upsert(ProductDraft draft) {
    if (draft.isCreate) {
      final Product created = _fromDraft(draft, 'prd_local_${_seq++}',
          listedAt: DateTime.now());
      _items.insert(0, created);
      notifyListeners();
      return created;
    }

    final int i = _items.indexWhere((Product p) => p.id == draft.id);
    // An edit keeps the original listing date — editing is not re-listing.
    final Product updated = _fromDraft(
      draft,
      draft.id!,
      listedAt: i < 0 ? DateTime.now() : _items[i].listedAt,
    );
    if (i < 0) {
      _items.insert(0, updated);
    } else {
      _items[i] = updated;
    }
    notifyListeners();
    return updated;
  }

  void setActive(String id, bool active) {
    // Both Set.remove and Set.add report whether anything actually changed.
    final bool changed = active ? _inactive.remove(id) : _inactive.add(id);
    if (changed) notifyListeners();
  }

  static Product _fromDraft(
    ProductDraft d,
    String id, {
    DateTime? listedAt,
  }) =>
      Product(
        id: id,
        category: d.category,
        brand: d.brand,
        name: d.name,
        modelCode: d.modelCode,
        rating: d.rating,
        warrantyYears: d.warrantyYears,
        warrantyNote: d.warrantyNote,
        rangeKm: d.rangeKm,
        topSpeedKmph: d.topSpeedKmph,
        chargingTime: d.chargingTime,
        batteryCapacity: d.batteryCapacity,
        motorPower: d.motorPower,
        loadCapacityKg: d.loadCapacityKg,
        colors: d.colors,
        highlights: d.highlights,
        listedAt: listedAt,
      );
}
