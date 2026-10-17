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
  ApiClient({required this.tokens, http.Client? client, String? baseUrl})
    : _client = client ?? http.Client(),
      _base = '${baseUrl ?? defaultApiUrl}/api/v1';

  final TokenStore tokens;
  final http.Client _client;
  final String _base;

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

  Future<Map<String, dynamic>> delete(String path) => _send('DELETE', path);

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
  }) async {
    final token = await _token();
    final uri = Uri.parse('$_base$path').replace(queryParameters: query);
    final request = http.Request(method, uri)
      ..headers['Accept'] = 'application/json'
      ..headers['Content-Type'] = 'application/json';
    if (token != null) request.headers['Authorization'] = 'Bearer $token';
    if (body != null) request.body = jsonEncode(body);
    return _execute(request, token);
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
    return _execute(request, token, timeout: const Duration(seconds: 120));
  }

  Future<Map<String, dynamic>> _execute(
    http.BaseRequest request,
    String? token, {
    Duration timeout = const Duration(seconds: 20),
  }) async {
    http.Response response;
    try {
      response = await http.Response.fromStream(
        await _client.send(request).timeout(timeout),
      );
    } catch (_) {
      throw const ApiException(
        0,
        'network_error',
        'Connexion impossible. Vérifiez votre réseau et réessayez.',
      );
    }

    if (response.statusCode == 204 || response.body.isEmpty) return const {};
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
      throw ApiException(
        response.statusCode,
        (payload['code'] as String?) ?? 'unknown_error',
        (payload['message'] as String?) ?? 'Une erreur est survenue.',
        _errors(payload['errors']),
      );
    }
    return payload;
  }

  Map<String, List<String>> _errors(Object? raw) {
    if (raw is! Map) return const {};
    return raw.map(
      (k, v) =>
          MapEntry('$k', v is List ? v.map((e) => '$e').toList() : ['$v']),
    );
  }
}
