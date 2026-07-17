import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import 'api_service.dart';

const _kTokenKey = 'auth_token';
const _kUserKey = 'auth_user';
const kSuperAdminRoleId = 1;
const kManagerRoleId = 2;
const kStaffRoleId = 3;
const kClientRoleId = 4;

/// Holds the current session and notifies listeners whenever the signed-in
/// user changes, so screens (home header, account tab) can react live.
class AuthService extends ChangeNotifier {
  AuthService._();

  static final AuthService instance = AuthService._();

  UserModel? _currentUser;
  bool _initialized = false;

  UserModel? get currentUser => _currentUser;
  bool get isLoggedIn => _currentUser != null;
  bool get isInitialized => _initialized;

  /// Restores a previously saved session from disk. Call once at app start.
  Future<void> restoreSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_kTokenKey);
    final userJson = prefs.getString(_kUserKey);

    if (token != null && userJson != null) {
      ApiService.setToken(token);
      _currentUser = UserModel.fromJson(
        jsonDecode(userJson) as Map<String, dynamic>,
      );
    }

    _initialized = true;
    notifyListeners();
  }

  Future<void> login({required String email, required String password}) async {
    final response = await ApiService.post('/login', {
      'email': email,
      'password': password,
    });
    await _persistSession(response as Map<String, dynamic>);
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await ApiService.post('/register', {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'role_id': kClientRoleId,
    });
    await _persistSession(response as Map<String, dynamic>);
  }

  Future<void> updateProfile({
    required String name,
    required String email,
    String? password,
    String? passwordConfirmation,
  }) async {
    final response = await ApiService.patch('/profile', {
      'name': name,
      'email': email,
      if (password != null && password.isNotEmpty) 'password': password,
      if (passwordConfirmation != null && passwordConfirmation.isNotEmpty)
        'password_confirmation': passwordConfirmation,
    });

    final user = UserModel.fromJson(response as Map<String, dynamic>);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kUserKey, jsonEncode(user.toJson()));

    _currentUser = user;
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await ApiService.post('/logout', {});
    } catch (_) {
      // Even if the network call fails, clear the local session below.
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kTokenKey);
    await prefs.remove(_kUserKey);

    ApiService.setToken(null);
    _currentUser = null;
    notifyListeners();
  }

  Future<void> _persistSession(Map<String, dynamic> response) async {
    final token = response['token'] as String;
    final user = UserModel.fromJson(response['user'] as Map<String, dynamic>);

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kTokenKey, token);
    await prefs.setString(_kUserKey, jsonEncode(user.toJson()));

    ApiService.setToken(token);
    _currentUser = user;
    notifyListeners();
  }
}
