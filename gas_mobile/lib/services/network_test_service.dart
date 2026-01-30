import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'dart:async';
import 'dart:io';
import 'package:tabungan/config/api.dart';

/// Network diagnostics service untuk test koneksi ke backend
class NetworkTestService {
  static const String _tag = '🌐 NetworkTestService';

  /// Test koneksi dasar ke backend pada startup
  static Future<void> testBackendConnectivity() async {
    if (!kDebugMode) return; // Only run in debug mode

    print('$_tag: Memulai diagnostik koneksi network...');
    print('$_tag: Base URL = ${Api.baseUrl}');

    // Test 1: DNS Resolution
    await _testDnsResolution();

    // Test 2: Basic HTTP Connection
    await _testBasicConnection();

    // Test 3: Login Endpoint
    await _testLoginEndpoint();
  }

  /// Test DNS resolution ke server
  static Future<void> _testDnsResolution() async {
    try {
      print('$_tag: [1/3] Testing DNS resolution...');
      
      // Extract IP/hostname dari base URL
      final uri = Uri.parse(Api.baseUrl);
      final host = uri.host;
      final port = uri.port;

      print('   Host: $host');
      print('   Port: $port');

      // Attempt DNS lookup
      final addresses = await InternetAddress.lookup(host);
      
      if (addresses.isNotEmpty) {
        print('   ✅ DNS Resolved: ${addresses.map((a) => a.address).join(", ")}');
      } else {
        print('   ❌ DNS Resolution Failed: No addresses found');
      }
    } catch (e) {
      print('   ❌ DNS Error: $e');
    }
  }

  /// Test basic HTTP connection
  static Future<void> _testBasicConnection() async {
    try {
      print('$_tag: [2/3] Testing basic HTTP connection...');
      
      final testUrl = Uri.parse('${Api.baseUrl}/ping.php');
      print('   Target: $testUrl');

      final response = await http
          .get(testUrl)
          .timeout(const Duration(seconds: 10));

      print('   Status Code: ${response.statusCode}');
      print('   Response: ${response.body.substring(0, response.body.length > 200 ? 200 : response.body.length)}');

      if (response.statusCode == 200) {
        print('   ✅ Basic Connection Success');
      } else {
        print('   ⚠️ Server responded with status ${response.statusCode}');
      }
    } on SocketException catch (e) {
      print('   ❌ Socket Error: ${e.message}');
      print('      code: ${e.osError?.errorCode ?? "unknown"}');
      print('      details: ${e.osError?.message ?? "no details"}');
    } on TimeoutException {
      print('   ⏱️ Timeout: Server took too long to respond');
    } catch (e) {
      print('   ❌ Unexpected Error: $e');
    }
  }

  /// Test login endpoint
  static Future<void> _testLoginEndpoint() async {
    try {
      print('$_tag: [3/3] Testing login endpoint...');
      
      final loginUrl = Uri.parse(Api.login);
      print('   Target: $loginUrl');

      // Use dummy credentials
      final response = await http
          .post(
            loginUrl,
            body: {
              'nohp': 'test',
              'pass': 'test',
            },
          )
          .timeout(const Duration(seconds: 10));

      print('   Status Code: ${response.statusCode}');
      print('   Response: ${response.body.substring(0, response.body.length > 200 ? 200 : response.body.length)}');

      if (response.statusCode == 200) {
        print('   ✅ Login Endpoint Reachable');
      } else {
        print('   ⚠️ Login endpoint responded with status ${response.statusCode}');
      }
    } on SocketException catch (e) {
      print('   ❌ Socket Error: ${e.message}');
      print('      code: ${e.osError?.errorCode ?? "unknown"}');
    } on TimeoutException {
      print('   ⏱️ Timeout: Login endpoint took too long');
    } catch (e) {
      print('   ❌ Error: $e');
    }
  }
}
