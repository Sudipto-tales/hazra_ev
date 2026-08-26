import 'dart:async';
import 'dart:convert';
import 'dart:io' show File, SocketException;

import 'package:http/http.dart' as http;

import '../../core/config/api_config.dart';
import 'api_exception.dart';
import 'token_store.dart';

/// One response, unwrapped.
///
/// `meta` is not decoration: the dashboard's whole TeamOverview aggregate rides
/// in `meta.totals`, badge counts ride in `meta.total`, and paging rides in
/// `meta.nextCursor`. Throwing it away would mean adding routes back.
class ApiResult {
  const ApiResult(this.data, this.meta);

  final dynamic data;
  final Map<String, dynamic> meta;

  Map<String, dynamic> get map => Map<String, dynamic>.from(data as Map);

  List<Map<String, dynamic>> get list => (data as List<dynamic>)
      .map((dynamic e) => Map<String, dynamic>.from(e as Map))
      .toList();

  int get total => (meta['total'] as num?)?.toInt() ?? 0;

  String? get nextCursor => meta['nextCursor'] as String?;
}

/// Talks to `website/api`.
///
/// Everything the contract standardises lives here so no repository repeats it:
/// the `{data, meta, error}` envelope, the bearer token, single-flight refresh
/// on a 401, and `Idempotency-Key` on the retryable writes.
class ApiClient {
  ApiClient({required TokenStore tokens, http.Client? client})
      : _tokens = tokens,
        _http = client ?? http.Client();

  final TokenStore _tokens;
  final http.Client _http;

  /// Fired when the refresh token is dead too — the shell listens and bounces
  /// back to sign-in rather than showing an error on every screen at once.
  void Function()? onSessionExpired;

  /// Guards against a burst of parallel 401s each firing its own refresh.
  Future<bool>? _refreshInFlight;

  static const Duration _timeout = Duration(seconds: 20);

  /// Uploads get their own budget. Eight megabytes of photos over a field
  /// team's mobile data does not finish in 20 seconds, and timing out a
  /// half-sent multipart body is how attachments get lost.
  static const Duration _uploadTimeout = Duration(minutes: 3);

  AuthSession? get session => _tokens.session;

  bool get isSignedIn => _tokens.isSignedIn;

  // ------------------------------------------------------------------ verbs

  Future<ApiResult> get(String path, {Map<String, dynamic>? query}) =>
      _send('GET', path, query: query);

  Future<ApiResult> post(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    String? idempotencyKey,
  }) =>
      _send('POST', path, body: body, query: query, idempotencyKey: idempotencyKey);

  Future<ApiResult> patch(String path, {Object? body}) =>
      _send('PATCH', path, body: body);

  Future<ApiResult> put(String path, {Object? body}) =>
      _send('PUT', path, body: body);

  Future<ApiResult> delete(String path) => _send('DELETE', path);

  // --------------------------------------------------------------- sessions

  /// `POST /auth/login`. Stores the session on success.
  Future<AuthSession> login({
    required String email,
    required String password,
    Map<String, dynamic>? device,
  }) async {
    final ApiResult result = await _send(
      'POST',
      '/auth/login',
      body: <String, dynamic>{
        'email': email,
        'password': password,
        if (device != null) 'device': device,
      },
      authenticated: false,
    );

    final AuthSession session = AuthSession.fromLogin(result.map);
    await _tokens.save(session);
    return session;
  }

  /// Best-effort: a failed logout must still clear the device.
  Future<void> logout() async {
    final AuthSession? current = _tokens.session;

    if (current != null && current.refreshToken.isNotEmpty) {
      try {
        await _send(
          'POST',
          '/auth/logout',
          body: <String, dynamic>{'refreshToken': current.refreshToken},
        );
      } on ApiException {
        // The token may already be revoked or expired. Nothing to salvage.
      }
    }

    await _tokens.clear();
  }

  /// Rotates the pair. The server invalidates the old refresh token, so the
  /// replacement has to be written back before any other call uses it.
  Future<bool> refresh() {
    return _refreshInFlight ??= _refresh().whenComplete(() {
      _refreshInFlight = null;
    });
  }

  Future<bool> _refresh() async {
    final AuthSession? current = _tokens.session;

    if (current == null || current.refreshToken.isEmpty) {
      return false;
    }

    try {
      final ApiResult result = await _send(
        'POST',
        '/auth/refresh',
        body: <String, dynamic>{'refreshToken': current.refreshToken},
        authenticated: false,
        allowRetry: false,
      );

      await _tokens.save(AuthSession.fromLogin(result.map));
      return true;
    } on ApiException {
      await _tokens.clear();
      return false;
    }
  }

  // ----------------------------------------------------------- multipart

  /// `POST /reports/{id}/images`. A report submits while its images are still
  /// queued, so this is deliberately a separate call from the report itself.
  ///
  /// Returns the stored image objects the server echoes back, in order. The
  /// caller compares that count against what it sent: the server writes each
  /// part as it validates it and aborts on the first bad one, so a short list
  /// is a partial upload.
  Future<List<Map<String, dynamic>>> uploadReportImages(
    String reportId,
    List<String> filePaths, {
    bool allowRetry = true,
  }) async {
    if (filePaths.isEmpty) {
      return <Map<String, dynamic>>[];
    }

    final Uri uri = _uri('/reports/$reportId/images', null);

    // A picked file the OS has since reclaimed (the camera cache is not ours
    // to keep) used to be skipped here, which let a request with nothing in it
    // return an empty list — indistinguishable, to the caller, from a clean
    // upload of zero images. Fail loudly instead: the seller picked it, so
    // losing it silently is the one outcome that must not happen.
    for (final String path in filePaths) {
      if (!File(path).existsSync()) {
        throw ApiException(
          code: 'ATTACHMENT_MISSING',
          message: 'A picked image is no longer on this device: $path',
        );
      }
    }

    // `MultipartRequest` streams its files and cannot be sent twice, so the
    // request is built per attempt. That is what lets the 401 path below
    // replay the way every JSON verb in `_send` already does.
    Future<http.MultipartRequest> build() async {
      final http.MultipartRequest request = http.MultipartRequest('POST', uri);

      request.headers.addAll(await _headers(authenticated: true, json: false));

      for (final String path in filePaths) {
        // `images[]` is the wire name PHP folds into `$_FILES['images']` with
        // array-shaped members, which is exactly what
        // `ReportsController::images` reads (it handles both the array and the
        // single-file shape). Content type is left to `http` to infer from the
        // extension — the server ignores the declared type and sniffs the
        // bytes with `getimagesize` regardless.
        request.files.add(await http.MultipartFile.fromPath('images[]', path));
      }

      return request;
    }

    http.Response response;

    try {
      final http.StreamedResponse streamed =
          await (await build()).send().timeout(_uploadTimeout);
      response = await http.Response.fromStream(streamed);
    } on SocketException catch (e) {
      throw ApiException.network(e, uri.toString());
    } on TimeoutException catch (e) {
      throw ApiException.network(e, uri.toString());
    } on http.ClientException catch (e) {
      throw ApiException.network(e, uri.toString());
    }

    if (response.statusCode == 401 && allowRetry) {
      if (await refresh()) {
        return uploadReportImages(reportId, filePaths, allowRetry: false);
      }

      onSessionExpired?.call();
    }

    return _decode(response, uri).list;
  }

  // ------------------------------------------------------------- internals

  Future<ApiResult> _send(
    String method,
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    String? idempotencyKey,
    bool authenticated = true,
    bool allowRetry = true,
  }) async {
    final Uri uri = _uri(path, query);

    http.Response response;

    try {
      response = await _dispatch(
        method,
        uri,
        body,
        await _headers(
          authenticated: authenticated,
          idempotencyKey: idempotencyKey,
        ),
      );
    } on SocketException catch (e) {
      throw ApiException.network(e, uri.toString());
    } on TimeoutException catch (e) {
      throw ApiException.network(e, uri.toString());
    } on http.ClientException catch (e) {
      throw ApiException.network(e, uri.toString());
    }

    // The access token aged out mid-session. Rotate once and replay; a second
    // 401 means the refresh token is gone too.
    if (response.statusCode == 401 && authenticated && allowRetry) {
      if (await refresh()) {
        return _send(
          method,
          path,
          body: body,
          query: query,
          idempotencyKey: idempotencyKey,
          authenticated: authenticated,
          allowRetry: false,
        );
      }

      onSessionExpired?.call();
    }

    return _decode(response, uri);
  }

  Future<http.Response> _dispatch(
    String method,
    Uri uri,
    Object? body,
    Map<String, String> headers,
  ) {
    final String? encoded = body == null ? null : jsonEncode(body);

    return switch (method) {
      'GET' => _http.get(uri, headers: headers).timeout(_timeout),
      'POST' => _http.post(uri, headers: headers, body: encoded).timeout(_timeout),
      'PATCH' => _http.patch(uri, headers: headers, body: encoded).timeout(_timeout),
      'PUT' => _http.put(uri, headers: headers, body: encoded).timeout(_timeout),
      'DELETE' => _http.delete(uri, headers: headers).timeout(_timeout),
      _ => throw ArgumentError('Unsupported method $method'),
    };
  }

  Future<Map<String, String>> _headers({
    required bool authenticated,
    bool json = true,
    String? idempotencyKey,
  }) async {
    final Map<String, String> headers = <String, String>{
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      if (idempotencyKey != null) 'Idempotency-Key': idempotencyKey,
    };

    if (authenticated) {
      final AuthSession? current = _tokens.session;

      // Refresh proactively when the token is already known to be stale — one
      // round trip saved over waiting for the 401.
      if (current != null && current.isExpired) {
        await refresh();
      }

      final String? token = _tokens.session?.accessToken;
      if (token != null) {
        headers['Authorization'] = 'Bearer $token';
      }
    }

    return headers;
  }

  Uri _uri(String path, Map<String, dynamic>? query) {
    final Uri base = Uri.parse('${ApiConfig.baseUrl}$path');

    if (query == null || query.isEmpty) {
      return base;
    }

    // Drop nulls and empty strings rather than sending `?status=`, which the
    // API would read as a present-but-blank filter.
    final Map<String, String> params = <String, String>{};

    query.forEach((String key, dynamic value) {
      if (value == null) return;
      if (value is String && value.isEmpty) return;
      if (value is Iterable) {
        if (value.isEmpty) return;
        params[key] = value.join(',');
        return;
      }
      params[key] = '$value';
    });

    return base.replace(queryParameters: <String, String>{
      ...base.queryParameters,
      ...params,
    });
  }

  ApiResult _decode(http.Response response, Uri uri) {
    // 204 is a real success with no body — POST /tracking/health uses it.
    if (response.statusCode == 204 || response.body.isEmpty) {
      return const ApiResult(null, <String, dynamic>{});
    }

    Map<String, dynamic> envelope;

    try {
      envelope = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {
      // A PHP fatal or a proxy error page — not the contract shape.
      throw ApiException(
        code: 'MALFORMED_RESPONSE',
        status: response.statusCode,
        message: 'Expected the {data, meta, error} envelope from $uri but got: '
            '${response.body.substring(0, response.body.length.clamp(0, 300))}',
      );
    }

    final dynamic error = envelope['error'];

    if (error is Map) {
      throw ApiException(
        code: error['code'] as String? ?? 'UNKNOWN',
        message: error['message'] as String? ?? 'Request failed',
        status: response.statusCode,
        field: error['field'] as String?,
      );
    }

    if (response.statusCode >= 400) {
      throw ApiException(
        code: 'HTTP_${response.statusCode}',
        message: 'Request to $uri failed',
        status: response.statusCode,
      );
    }

    return ApiResult(
      envelope['data'],
      Map<String, dynamic>.from(
        (envelope['meta'] as Map<String, dynamic>?) ?? <String, dynamic>{},
      ),
    );
  }

  void dispose() => _http.close();
}
