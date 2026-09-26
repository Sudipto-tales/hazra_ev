/// Employee identity + org placement. Mirrors `GET /api/employee/profile`.
class Employee {
  const Employee({
    required this.id,
    required this.employeeCode,
    required this.name,
    required this.designation,
    required this.department,
    required this.email,
    required this.phone,
    required this.avatarUrl,
    required this.bannerUrl,
    required this.joinedOn,
    required this.reportingTo,
    required this.region,
    required this.bloodGroup,
    required this.address,
    this.mustChangePassword = false,
  });

  final String id;

  /// Human-facing ID shown next to the name everywhere (`EMP-1042`).
  final String employeeCode;
  final String name;
  final String designation;
  final String department;
  final String email;
  final String phone;

  /// Remote URL — null/empty falls back to initials in the avatar widget.
  final String avatarUrl;
  final String bannerUrl;
  final DateTime joinedOn;
  final String reportingTo;
  final String region;
  final String bloodGroup;
  final String address;

  /// Set only on the signed-in principal (`GET /me`, login), and only when
  /// the account is still on a password the server generated rather than one
  /// anybody chose. Roster rows never carry it, so they read false.
  final bool mustChangePassword;

  String get initials {
    final List<String> parts =
        name.trim().split(RegExp(r'\s+')).where((String p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1)).toUpperCase();
  }

  String get firstName => name.split(' ').first;

  /// Used by the admin employee form, which rebuilds the record field by
  /// field while keeping the id and image URLs.
  Employee copyWith({
    String? employeeCode,
    String? name,
    String? designation,
    String? department,
    String? email,
    String? phone,
    String? avatarUrl,
    String? bannerUrl,
    DateTime? joinedOn,
    String? reportingTo,
    String? region,
    String? bloodGroup,
    String? address,
    bool? mustChangePassword,
  }) {
    return Employee(
      id: id,
      employeeCode: employeeCode ?? this.employeeCode,
      name: name ?? this.name,
      designation: designation ?? this.designation,
      department: department ?? this.department,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      avatarUrl: avatarUrl ?? this.avatarUrl,
      bannerUrl: bannerUrl ?? this.bannerUrl,
      joinedOn: joinedOn ?? this.joinedOn,
      reportingTo: reportingTo ?? this.reportingTo,
      region: region ?? this.region,
      bloodGroup: bloodGroup ?? this.bloodGroup,
      address: address ?? this.address,
      mustChangePassword: mustChangePassword ?? this.mustChangePassword,
    );
  }
}
