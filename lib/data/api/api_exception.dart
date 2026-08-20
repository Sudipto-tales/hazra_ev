/// A failed call, carrying the contract's stable machine-readable `code`.
///
/// The API answers errors as
/// `{"data": null, "error": {"code": …, "message": …, "field": …}}`
/// (API plan §1.1). `code` is what the app branches on; `message` is only ever
/// shown, never parsed.
class ApiException implements Exception {
  const ApiException({
    required this.code,
    required this.message,
    this.status,
    this.field,
  });

  /// The request never reached the server — wrong host, server down, phone off
  /// the network. By far the most common failure in development, so it gets its
  /// own code rather than surfacing as a raw SocketException.
  factory ApiException.network(Object error, String url) => ApiException(
        code: 'NETWORK_UNREACHABLE',
        message: 'Could not reach $url. Is the API running and listening on '
            'all interfaces (php vayu run --host 0.0.0.0)?\n$error',
      );

  final String code;
  final String message;
  final int? status;

  /// Which request field failed validation, when the server named one.
  final String? field;

  bool get isUnauthenticated => code == 'UNAUTHENTICATED' || status == 401;

  bool get isForbidden => code == 'FORBIDDEN' || status == 403;

  bool get isNotFound => status == 404;

  bool get isNetwork => code == 'NETWORK_UNREACHABLE';

  @override
  String toString() => 'ApiException($code${status == null ? '' : ' $status'}): $message';
}
