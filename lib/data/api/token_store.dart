import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// The access/refresh pair and the principal that came back with them.
///
/// Persisted so a relaunch does not need a fresh sign-in. The access token is
/// short-lived (JWT_TTL, one hour by default); the refresh token is what
/// actually survives, and it is single-use — the server rotates it on every
/// refresh, so the replacement must be written back here.
class AuthSession {
  const AuthSession({
    required this.accessToken,
    required this.refreshToken,
    required this.expiresAt,
    required this.principal,
  });

  factory AuthSession.fromLogin(Map<String, dynamic> data) {
    final int expiresIn = (data['expiresIn'] as num?)?.toInt() ?? 3600;

    return AuthSession(
      accessToken: data['accessToken'] as String,
      refreshToken: data['refreshToken'] as String? ?? '',
      // 30 s of slack so a token does not expire mid-flight.
      expiresAt: DateTime.now().add(Duration(seconds: expiresIn - 30)),
      principal: Map<String, dynamic>.from(data['principal'] as Map),
    );
  }

  factory AuthSession.fromJson(Map<String, dynamic> json) => AuthSession(
        accessToken: json['accessToken'] as String,
        refreshToken: json['refreshToken'] as String? ?? '',
        expiresAt: DateTime.parse(json['expiresAt'] as String),
        principal: Map<String, dynamic>.from(json['principal'] as Map),
      );

  final String accessToken;
  final String refreshToken;
  final DateTime expiresAt;

  /// `Employee` or `AdminUser`, discriminated by `principal['type']`.
  final Map<String, dynamic> principal;

  bool get isAdmin => principal['type'] == 'admin';

  bool get isExpired => DateTime.now().isAfter(expiresAt);

  Map<String, dynamic> toJson() => <String, dynamic>{
        'accessToken': accessToken,
        'refreshToken': refreshToken,
        'expiresAt': expiresAt.toIso8601String(),
        'principal': principal,
      };
}

class TokenStore {
  TokenStore._(this._prefs, this._session);

  static const String _key = 'auth.session.v1';

  static Future<TokenStore> open() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final String? raw = prefs.getString(_key);

    AuthSession? session;
    if (raw != null) {
      try {
        session = AuthSession.fromJson(
          jsonDecode(raw) as Map<String, dynamic>,
        );
      } catch (_) {
        // A stored session from an older shape is worth nothing; drop it
        // rather than crashing on launch.
        await prefs.remove(_key);
      }
    }

    return TokenStore._(prefs, session);
  }

  final SharedPreferences _prefs;
  AuthSession? _session;

  AuthSession? get session => _session;

  bool get isSignedIn => _session != null;

  Future<void> save(AuthSession session) async {
    _session = session;
    await _prefs.setString(_key, jsonEncode(session.toJson()));
  }

  Future<void> clear() async {
    _session = null;
    await _prefs.remove(_key);
  }
}
