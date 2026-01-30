import 'package:http/http.dart' as http;
import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'dart:convert';

class HttpHelper {
  static const Duration requestTimeout = Duration(seconds: 30);

  /// POST request dengan logging DETAIL
  static Future<http.Response> post(
    Uri url, {
    Map<String, String>? headers,
    dynamic body,
  }) async {
    final timestamp = DateTime.now().toIso8601String();
    
    try {
      if (kDebugMode) {
        print('\n' + ('='*80));
        print('[$timestamp] 📤 POST REQUEST');
        // Extract base URL correctly (scheme + host)
        final scheme = url.scheme;
        final host = url.host;
        final baseUrlLog = '$scheme://$host';
        print('   🔗 BASE URL: $baseUrlLog');
        print('   URL: $url');
        print('   Method: POST');
        print('   Headers: $headers');
        if (body is String) {
          print('   Body (String): $body');
        } else if (body is Map) {
          print('   Body (Form): $body');
        } else {
          print('   Body: $body');
        }
        print('   Timeout: ${requestTimeout.inSeconds}s');
      }
      
      final response = await http
          .post(url, headers: headers, body: body)
          .timeout(requestTimeout);
      
      if (kDebugMode) {
        print('\n📥 RESPONSE RECEIVED:');
        print('   Status Code: ${response.statusCode}');
        print('   Status Reason: ${response.reasonPhrase}');
        print('   Headers: ${response.headers}');
        print('   Body Length: ${response.body.length} bytes');
        
        // CRITICAL: Always print RAW body first
        print('   📋 RAW BODY: ${response.body}');
        
        // Then try to parse as JSON for logging
        try {
          final jsonData = jsonDecode(response.body);
          print('   ✅ Body (Parsed JSON): $jsonData');
        } catch (e) {
          print('   ⚠️ Not valid JSON: $e');
        }
        print('='*80 + '\n');
      }
      
      // Validate status code
      if (response.statusCode != 200) {
        if (kDebugMode) {
          print('⚠️ WARNING: HTTP Status ${response.statusCode}');
          print('   Expected 200, got ${response.statusCode}');
        }
      }
      
      return response;
    } on SocketException catch (e) {
      final errorMsg = '❌ SOCKET ERROR: ${e.message}';
      final details = 'errno: ${e.osError?.errorCode ?? "unknown"}, '
          'message: ${e.osError?.message ?? "no details"}';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('   Details: $details');
        print('='*80 + '\n');
      }
      throw HttpException('$errorMsg ($details)', uri: url);
    } on TimeoutException {
      final errorMsg = '⏱️ TIMEOUT: Request timeout setelah ${requestTimeout.inSeconds}s';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('   URL: $url');
        print('='*80 + '\n');
      }
      rethrow;
    } on HandshakeException catch (e) {
      final errorMsg = '🔐 HANDSHAKE ERROR: ${e.message}';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('='*80 + '\n');
      }
      throw HttpException(errorMsg, uri: url);
    } catch (e) {
      final errorMsg = '💥 UNEXPECTED ERROR: ${e.runtimeType} - $e';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('='*80 + '\n');
      }
      throw HttpException(errorMsg, uri: url);
    }
  }

  /// GET request dengan logging DETAIL
  static Future<http.Response> get(
    Uri url, {
    Map<String, String>? headers,
  }) async {
    final timestamp = DateTime.now().toIso8601String();
    
    try {
      if (kDebugMode) {
        print('\n' + ('='*80));
        print('[$timestamp] 📥 GET REQUEST');
        print('   🔗 FINAL BASE URL USED: ${url.toString().split('/').take(3).join('/')}//${url.host}');
        print('   URL: $url');
        print('   Method: GET');
        print('   Headers: $headers');
        print('   Timeout: ${requestTimeout.inSeconds}s');
      }
      
      final response = await http.get(url, headers: headers).timeout(requestTimeout);
      
      if (kDebugMode) {
        print('\n📥 RESPONSE RECEIVED:');
        print('   Status Code: ${response.statusCode}');
        print('   Status Reason: ${response.reasonPhrase}');
        print('   Headers: ${response.headers}');
        print('   Body Length: ${response.body.length} bytes');
        
        // CRITICAL: Always print RAW body first
        print('   📋 RAW BODY: ${response.body}');
        
        // Then try to parse as JSON for logging
        try {
          final jsonData = jsonDecode(response.body);
          print('   ✅ Body (Parsed JSON): $jsonData');
        } catch (e) {
          print('   ⚠️ Not valid JSON: $e');
        }
        print('='*80 + '\n');
      }
      
      // Validate status code
      if (response.statusCode != 200) {
        if (kDebugMode) {
          print('⚠️ WARNING: HTTP Status ${response.statusCode}');
          print('   Expected 200, got ${response.statusCode}');
        }
      }
      
      return response;
    } on SocketException catch (e) {
      final errorMsg = '❌ SOCKET ERROR: ${e.message}';
      final details = 'errno: ${e.osError?.errorCode ?? "unknown"}, '
          'message: ${e.osError?.message ?? "no details"}';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('   Details: $details');
        print('='*80 + '\n');
      }
      throw HttpException('$errorMsg ($details)', uri: url);
    } on TimeoutException {
      final errorMsg = '⏱️ TIMEOUT: Request timeout setelah ${requestTimeout.inSeconds}s';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('   URL: $url');
        print('='*80 + '\n');
      }
      rethrow;
    } on HandshakeException catch (e) {
      final errorMsg = '🔐 HANDSHAKE ERROR: ${e.message}';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('='*80 + '\n');
      }
      throw HttpException(errorMsg, uri: url);
    } catch (e) {
      final errorMsg = '💥 UNEXPECTED ERROR: ${e.runtimeType} - $e';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg');
        print('='*80 + '\n');
      }
      throw HttpException(errorMsg, uri: url);
    }
  }
}

