import 'dart:convert';
import 'dart:async';
import 'package:http/http.dart' as http;
import '../utils/constants.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;

  ApiException(this.message, [this.statusCode]);

  @override
  String toString() => message;
}

class ApiService {
  ApiService._();

  static String? _token;

  static void setToken(String? token) {
    _token = token;
  }

  static Map<String, String> _headers() {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (_token != null) 'Authorization': 'Bearer $_token',
    };
  }

  static Future<dynamic> get(String path) async {
    return _run(
      () => http
          .get(Uri.parse('$kApiBaseUrl$path'), headers: _headers())
          .timeout(const Duration(seconds: 15)),
    );
  }

  static Future<dynamic> post(String path, Map<String, dynamic> body) async {
    return _run(
      () => http
          .post(
            Uri.parse('$kApiBaseUrl$path'),
            headers: _headers(),
            body: jsonEncode(body),
          )
          .timeout(const Duration(seconds: 15)),
    );
  }

  static Future<dynamic> patch(String path, Map<String, dynamic> body) async {
    return _run(
      () => http
          .patch(
            Uri.parse('$kApiBaseUrl$path'),
            headers: _headers(),
            body: jsonEncode(body),
          )
          .timeout(const Duration(seconds: 15)),
    );
  }

  static Future<dynamic> delete(String path) async {
    return _run(
      () => http
          .delete(Uri.parse('$kApiBaseUrl$path'), headers: _headers())
          .timeout(const Duration(seconds: 15)),
    );
  }

  /// Submits a multipart/form-data request — used for endpoints that accept
  /// a file upload (e.g. a booking's proof-of-payment photo) alongside
  /// regular form fields.
  static Future<dynamic> postMultipart(
    String path,
    Map<String, String> fields, {
    required String filePath,
    required String fileFieldName,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$kApiBaseUrl$path'),
    );
    request.headers['Accept'] = 'application/json';
    if (_token != null) request.headers['Authorization'] = 'Bearer $_token';
    request.fields.addAll(fields);
    request.files.add(
      await http.MultipartFile.fromPath(fileFieldName, filePath),
    );

    final streamedResponse = await request.send().timeout(
      const Duration(seconds: 30),
    );
    final response = await http.Response.fromStream(streamedResponse);
    return _handleResponse(response);
  }

  static dynamic _handleResponse(http.Response response) {
    final bool hasBody = response.body.isNotEmpty;
    dynamic decoded;
    if (hasBody) {
      try {
        decoded = jsonDecode(response.body);
      } on FormatException {
        throw ApiException(
          'The server returned an invalid response.',
          response.statusCode,
        );
      }
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded;
    }

    throw ApiException(_extractErrorMessage(decoded), response.statusCode);
  }

  static Future<dynamic> _run(
    Future<http.Response> Function() request,
  ) async {
    try {
      return _handleResponse(await request());
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw ApiException('The server took too long to respond.');
    } on http.ClientException {
      throw ApiException(
        'Cannot connect to the server. Check the API address and CORS settings.',
      );
    }
  }

  static String _extractErrorMessage(dynamic decoded) {
    if (decoded is Map<String, dynamic>) {
      if (decoded['errors'] is Map && (decoded['errors'] as Map).isNotEmpty) {
        final firstField = (decoded['errors'] as Map).values.first;
        if (firstField is List && firstField.isNotEmpty) {
          return firstField.first.toString();
        }
      }
      if (decoded['message'] != null) {
        return decoded['message'].toString();
      }
    }
    return 'Something went wrong. Please try again.';
  }
}
