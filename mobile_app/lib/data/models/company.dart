/// A customer company. Visits and reports both hang off this.
class Company {
  const Company({
    required this.id,
    required this.name,
    required this.branches,
    required this.category,
  });

  final String id;
  final String name;
  final List<Branch> branches;

  /// e.g. `Distributor`, `Retail`, `Corporate`.
  final String category;

  Branch? branchById(String? id) {
    if (id == null) return null;
    for (final Branch b in branches) {
      if (b.id == id) return b;
    }
    return null;
  }
}

class Branch {
  const Branch({
    required this.id,
    required this.name,
    required this.address,
    required this.latitude,
    required this.longitude,
  });

  final String id;
  final String name;
  final String address;
  final double latitude;
  final double longitude;
}
