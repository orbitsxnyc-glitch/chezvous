import 'package:flutter/foundation.dart';

import '../core/api_client.dart';
import '../core/token_store.dart';

class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.phone,
    required this.roles,
    this.email,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
    id: '${json['id']}',
    name: '${json['name']}',
    phone: '${json['phone']}',
    email: json['email'] as String?,
    roles: (json['roles'] as List? ?? const []).map((e) => '$e').toList(),
  );

  final String id;
  final String name;
  final String phone;
  final String? email;
  final List<String> roles;
}

enum AuthStatus { unknown, signedOut, signedIn }

/// État d'authentification : jeton d'appareil révocable (pas de cookie sur mobile).
class AuthController extends ChangeNotifier {
  AuthController({
    required this.api,
    required this.tokens,
    this.deviceName = 'Application mobile',
  }) {
    api.onUnauthorized = _expire;
  }

  final ApiClient api;
  final TokenStore tokens;
  final String deviceName;

  AuthStatus status = AuthStatus.unknown;

  /// Vrai quand une session ouverte a été refusée par le serveur (jeton révoqué ou expiré) : l'écran l'annonce une fois.
  bool sessionExpired = false;
  AppUser? user;

  /// Restaure la session au démarrage ; un jeton refusé est effacé.
  Future<void> restore() async {
    String? token;
    try {
      token = await tokens.read();
    } catch (_) {
      // Trousseau illisible : on démarre déconnecté plutôt que de rester bloqué sur le chargement.
      return _set(AuthStatus.signedOut, null);
    }
    if (token == null) return _set(AuthStatus.signedOut, null);
    try {
      final me = await api.get('/me');
      _set(
        AuthStatus.signedIn,
        AppUser.fromJson(me['data'] as Map<String, dynamic>),
      );
    } on ApiException catch (e) {
      // Réseau coupé : on reste déconnecté sans détruire un jeton peut-être valide.
      if (e.status == 401) await tokens.clear();
      _set(AuthStatus.signedOut, null);
    }
  }

  Future<void> login(String phone, String password) async {
    final res = await api.post('/auth/login', {
      'phone': phone,
      'password': password,
      'device_name': deviceName,
    });
    final data = res['data'] as Map<String, dynamic>;
    await tokens.write(data['token'] as String);
    _set(
      AuthStatus.signedIn,
      AppUser.fromJson(data['user'] as Map<String, dynamic>),
    );
  }

  Future<void> register({
    required String name,
    required String phone,
    required String password,
    required bool agency,
    String? email,
    String? agencyName,
  }) async {
    await api.post('/auth/register', {
      'name': name,
      'phone': phone,
      'password': password,
      'account_type': agency ? 'agency' : 'individual',
      if (email != null && email.isNotEmpty) 'email': email,
      if (agency && agencyName != null) 'agency_name': agencyName,
    });
  }

  Future<void> verifyPhone(String phone, String code) =>
      api.post('/auth/otp/verify', {'phone': phone, 'code': code});

  Future<void> resendCode(String phone) =>
      api.post('/auth/otp/send', {'phone': phone, 'purpose': 'verify_phone'});

  Future<void> logout() async {
    try {
      await api.post('/auth/logout');
    } on ApiException {
      // Le jeton est effacé localement dans tous les cas.
    }
    await tokens.clear();
    _set(AuthStatus.signedOut, null);
  }

  /// Suppression du compte (exigée par les boutiques d'applications) : mot de passe demandé, données personnelles anonymisées côté serveur.
  Future<void> deleteAccount(String password) async {
    await api.delete('/me', body: {'password': password});
    await tokens.clear();
    _set(AuthStatus.signedOut, null);
  }

  void _expire() {
    tokens.clear();
    sessionExpired = status == AuthStatus.signedIn;
    _set(AuthStatus.signedOut, null);
  }

  void _set(AuthStatus s, AppUser? u) {
    status = s;
    user = u;
    notifyListeners();
  }
}
