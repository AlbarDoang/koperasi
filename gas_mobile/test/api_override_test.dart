import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/config/api.dart';

void main() {
  group('Api override behavior', () {
    setUp(() async {
      // Ensure shared preferences starts clean for each test
      SharedPreferences.setMockInitialValues({});
    });

    test('Default resolves to LAN when no env and no override', () async {
      await Api.init();
      // Default in project is the LAN IP
      expect(Api.baseUrl.contains('172.168.80.236'), isTrue,
          reason: 'Default base should be LAN when no env var set');
    });

    test('Setting emulator override updates baseUrl and is persisted', () async {
      await Api.init();
      await Api.setOverride(Api.overrideEmulator);
      expect(Api.baseUrl.contains('10.0.2.2'), isTrue,
          reason: 'Emulator override should set 10.0.2.2');

      // Simulate restart: reset base and re-init - override must persist
      Api.baseUrl = 'http://example.invalid';
      await Api.init();
      expect(Api.baseUrl.contains('10.0.2.2'), isTrue,
          reason: 'Emulator override should persist across init');
    });

    test('Setting LAN override updates baseUrl and is persisted', () async {
      await Api.init();
      await Api.setOverride(Api.overrideLan);
      expect(Api.baseUrl.contains('172.168.80.236'), isTrue,
          reason: 'LAN override should set LAN IP');

      Api.baseUrl = 'http://example.invalid';
      await Api.init();
      expect(Api.baseUrl.contains('172.168.80.236'), isTrue,
          reason: 'LAN override should persist across init');
    });

    test('Auto (or null) clears override and falls back to default', () async {
      await Api.init();
      await Api.setOverride(Api.overrideEmulator);
      expect(Api.baseUrl.contains('10.0.2.2'), isTrue);

      // Clear override
      await Api.setOverride(Api.overrideAuto);
      Api.baseUrl = 'http://example.invalid';
      await Api.init();
      expect(Api.baseUrl.contains('172.168.80.236'), isTrue,
          reason: 'Auto should remove override and fall back to default');
    });
  });
}
