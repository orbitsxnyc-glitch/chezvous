import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Stockage du jeton d'appareil. Jamais dans les préférences en clair ni dans les journaux.
abstract class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> clear();
}

/// Trousseau iOS / Keystore Android.
class SecureTokenStore implements TokenStore {
  SecureTokenStore([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'chezvous_device_token';
  final FlutterSecureStorage _storage;

  @override
  Future<String?> read() => _storage.read(key: _key);

  @override
  Future<void> write(String token) => _storage.write(key: _key, value: token);

  @override
  Future<void> clear() => _storage.delete(key: _key);
}

/// Pour les tests.
class MemoryTokenStore implements TokenStore {
  MemoryTokenStore([this._token]);

  String? _token;

  @override
  Future<String?> read() async => _token;

  @override
  Future<void> write(String token) async => _token = token;

  @override
  Future<void> clear() async => _token = null;
}
