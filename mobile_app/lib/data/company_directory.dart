import 'package:flutter/foundation.dart';

import 'models/models.dart';

/// Resolves `companyId` / `branchId` to the names the UI shows.
///
/// Visits and stops travel as ids only — `Present::visit` in `website/api`
/// returns `companyId` / `branchId` and no names — so something has to hold the
/// mapping. That used to be `MockData.companyName`, which answered for live ids
/// by falling through to `companies.first`: a real visit rendered a real but
/// *wrong* company name, silently. This is the replacement.
///
/// Backed by `GET /companies?include=branches` through
/// [EmployeeRepository.companies], loaded once and cached, because every call
/// site ([CompanyVisit] tiles, the report form, the admin route map) is a
/// synchronous `build()` that cannot await.
///
/// **An unknown id resolves to null, never to another company.** Callers pick
/// their own placeholder, or use [displayCompany] / [displayBranch].
class CompanyDirectory extends ChangeNotifier {
  CompanyDirectory(this._load);

  /// Prebuilt directory — for tests and for any caller that already holds the
  /// list and does not want a fetch.
  CompanyDirectory.seeded(List<Company> companies)
      : _load = (() async => companies) {
    _adopt(companies);
    _loaded = true;
  }

  final Future<List<Company>> Function() _load;

  final Map<String, Company> _byId = <String, Company>{};
  List<Company> _all = const <Company>[];

  bool _loaded = false;
  Object? _error;
  Future<void>? _inFlight;

  /// True once a fetch has succeeded. Lookups before this return null.
  bool get isLoaded => _loaded;

  /// Set when the last load failed. The directory stays usable (empty), so a
  /// company lookup degrades to a placeholder instead of taking a screen down.
  Object? get error => _error;

  List<Company> get all => _all;

  /// Loads once. Concurrent callers share the same request; a caller that
  /// arrives after a success returns immediately.
  Future<void> ensureLoaded() {
    if (_loaded) return Future<void>.value();
    return _inFlight ??= _fetch();
  }

  /// Forces a refetch — used after the roster could have changed.
  Future<void> refresh() {
    _inFlight = null;
    _loaded = false;
    return ensureLoaded();
  }

  Future<void> _fetch() async {
    try {
      _adopt(await _load());
      _loaded = true;
      _error = null;
    } catch (e) {
      // Swallowed on purpose: a directory miss must not fail the screen that
      // only wanted a label. `error` is there for anyone who cares.
      _error = e;
    } finally {
      _inFlight = null;
      notifyListeners();
    }
  }

  void _adopt(List<Company> companies) {
    _all = List<Company>.unmodifiable(companies);
    _byId
      ..clear()
      ..addEntries(
        companies.map((Company c) => MapEntry<String, Company>(c.id, c)),
      );
  }

  Company? company(String? id) => id == null ? null : _byId[id];

  /// Null when the id is unknown or the directory has not loaded.
  String? companyName(String? id) => company(id)?.name;

  Branch? branch(String? companyId, String? branchId) =>
      company(companyId)?.branchById(branchId);

  String? branchName(String? companyId, String? branchId) =>
      branch(companyId, branchId)?.name;

  /// Company name with a neutral placeholder. Never another company's name.
  String displayCompany(String? id, {String fallback = 'Unknown company'}) =>
      companyName(id) ?? fallback;

  /// Branch name with a neutral placeholder. An unset `branchId` is a company
  /// with no branch, which is legitimate — that is what [fallback] is for.
  String displayBranch(
    String? companyId,
    String? branchId, {
    String fallback = '—',
  }) =>
      branchName(companyId, branchId) ?? fallback;
}
