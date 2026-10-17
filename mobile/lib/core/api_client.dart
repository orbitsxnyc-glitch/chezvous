import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import 'token_store.dart';

/// Adresse de l'API : `flutter run --dart-define=API_URL=https://api.exemple.sn`.
/// 10.0.2.2 = la machine hôte vue depuis l'émulateur Android ; localhost convient au simulateur iOS.
const String defaultApiUrl = String.fromEnvironment(
  'API_URL',
  defaultValue: 'http://localhost:8000',
);

/// Erreur normalisée de l'API : { code, message, errors? }.
class ApiException implements Exception {
  const ApiException(
    this.status,
    this.code,
    this.message, [
    this.errors = const {},
  ]);

  final int status;
  final String code;
  final String message;
  final Map<String, List<String>> errors;

  /// Premier message d'erreur d'un champ, s'il existe.
  String? field(String name) => errors[name]?.firstOrNull;

  @override
  String toString() => 'ApiException($status, $code)';
}

class ApiClient {
  ApiClient({
    required this.tokens,
    http.Client? client,
    String? baseUrl,
    this.retryDelay = const Duration(milliseconds: 400),
    this.requestTimeout = const Duration(seconds: 20),
  }) : _client = client ?? http.Client(),
       _base = '${baseUrl ?? defaultApiUrl}/api/v1';

  final TokenStore tokens;
  final http.Client _client;
  final String _base;

  /// Pause avant la nouvelle tentative d'une lecture en échec passager (réglable pour les tests).
  final Duration retryDelay;

  /// Délai maximal d'une requête ordinaire (réglable pour les tests).
  final Duration requestTimeout;

  /// Appelée quand l'API répond 401 avec un jeton présent (jeton révoqué ou expiré).
  void Function()? onUnauthorized;

  /// Un trousseau illisible ne doit jamais bloquer les requêtes publiques (recherche, fiches) : sans jeton, on poursuit anonymement.
  Future<String?> _token() async {
    try {
      return await tokens.read();
    } catch (_) {
      return null;
    }
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, String>? query}) =>
      _send('GET', path, query: query);

  Future<Map<String, dynamic>> post(
    String path, [
    Map<String, dynamic>? body,
  ]) => _send('POST', path, body: body);

  Future<Map<String, dynamic>> patch(String path, Map<String, dynamic> body) =>
      _send('PATCH', path, body: body);

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? body,
  }) => _send('DELETE', path, body: body);

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
  }) async {
    final token = await _token();
    final uri = Uri.parse('$_base$path').replace(queryParameters: query);
    // Une requête ne s'envoie qu'une fois : on la reconstruit pour une nouvelle tentative.
    http.BaseRequest build() {
      final request = http.Request(method, uri)
        ..headers['Accept'] = 'application/json'
        ..headers['Content-Type'] = 'application/json';
      if (token != null) request.headers['Authorization'] = 'Bearer $token';
      if (body != null) request.body = jsonEncode(body);
      return request;
    }

    // Seules les lectures sont rejouées automatiquement : rejouer un envoi pourrait le faire en double.
    return _execute(build, token, retry: method == 'GET');
  }

  /// Envoi de fichiers (photos) en multipart ; le champ est `images[]` comme attendu par l'API.
  Future<Map<String, dynamic>> upload(
    String path,
    List<http.MultipartFile> files,
  ) async {
    final token = await _token();
    final request = http.MultipartRequest('POST', Uri.parse('$_base$path'))
      ..headers['Accept'] = 'application/json'
      ..files.addAll(files);
    if (token != null) request.headers['Authorization'] = 'Bearer $token';
    return _execute(
      () => request,
      token,
      timeout: const Duration(seconds: 120),
    );
  }

  Future<Map<String, dynamic>> _execute(
    http.BaseRequest Function() build,
    String? token, {
    Duration? timeout,
    bool retry = false,
  }) async {
    var attempt = 0;
    while (true) {
      attempt++;
      try {
        return await _attempt(build(), token, timeout ?? requestTimeout);
      } on ApiException catch (e) {
        // Panne passagère (réseau, lenteur, 502/503/504) : une seule nouvelle tentative, après une courte pause.
        final transient =
            e.code == 'network_error' ||
            e.code == 'timeout' ||
            e.status == 502 ||
            e.status == 503 ||
            e.status == 504;
        if (!retry || !transient || attempt >= 2) rethrow;
        await Future<void>.delayed(retryDelay);
      }
    }
  }

  Future<Map<String, dynamic>> _attempt(
    http.BaseRequest request,
    String? token,
    Duration timeout,
  ) async {
    http.Response response;
    try {
      response = await http.Response.fromStream(
        await _client.send(request).timeout(timeout),
      );
    } on TimeoutException {
      throw const ApiException(
        0,
        'timeout',
        'Le serveur met trop de temps à répondre. Réessayez dans un instant.',
      );
    } catch (_) {
      throw const ApiException(
        0,
        'network_error',
        'Connexion impossible. Vérifiez votre réseau et réessayez.',
      );
    }

    if (response.statusCode == 204 || response.body.isEmpty) {
      if (response.statusCode >= 400) throw _failure(response, const {});
      return const {};
    }
    Object? decoded;
    try {
      decoded = jsonDecode(utf8.decode(response.bodyBytes));
    } catch (_) {
      decoded = null;
    }
    final payload = decoded is Map<String, dynamic>
        ? decoded
        : <String, dynamic>{};

    if (response.statusCode >= 400) {
      if (response.statusCode == 401 && token != null) onUnauthorized?.call();
      throw _failure(response, payload);
    }
    return payload;
  }

  /// Erreur lisible : le message du serveur s'il existe, sinon un texte adapté au type de panne.
  ApiException _failure(http.Response response, Map<String, dynamic> payload) {
    final status = response.statusCode;
    final fallback = switch (status) {
      429 => 'Trop de demandes en peu de temps. Patientez quelques secondes puis réessayez.',
      >= 500 => 'Le service rencontre un problème. Réessayez dans un instant.',
      _ => 'Une erreur est survenue.',
    };
    return ApiException(
      status,
      (payload['code'] as String?) ??
          (status == 429 ? 'throttled' : 'unknown_error'),
      status >= 500 || status == 429
          ? fallback
          : (payload['message'] as String?) ?? fallback,
      _errors(payload['errors']),
    );
  }

  Map<String, List<String>> _errors(Object? raw) {
    if (raw is! Map) return const {};
    return raw.map(
      (k, v) =>
          MapEntry('$k', v is List ? v.map((e) => '$e').toList() : ['$v']),
    );
  }
}
