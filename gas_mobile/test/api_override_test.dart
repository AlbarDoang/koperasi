import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/config/api.dart';

void main() {
  group('Api override behavior', () {
    setUp(() async {
      // Ensure shared preferences starts clean for each test
      SharedPreferences.setMockInitialValues({});
    });

    test('Default resolves to ngrok when no env and no override', () async {
      await Api.init();
      // Default in project is now ngrok URL
      expect(Api.baseUrl.contains('tetrapodic-riotous-rosario.ngrok-free.dev'), isTrue,
          reason: 'Default base should be ngrok URL');
    });

    test('Setting emulator override updates baseUrl and is persisted', () async {
      await Api.init();
      await Api.setOverride(Api.overrideEmulator);
      expect(Api.baseUrl.contains('tetrapodic-riotous-rosario.ngrok-free.dev'), isTrue,
          reason: 'Emulator override should set ngrok URL');

      // Simulate restart: reset base and re-init - override must persist
      Api.baseUrl = 'http://example.invalid';
      await Api.init();
      expect(Api.baseUrl.contains('tetrapodic-riotous-rosario.ngrok-free.dev'), isTrue,
          reason: 'Emulator override should persist across init');
    });

    test('Setting LAN override updates baseUrl and is persisted', () async {
      await Api.init();
      await Api.setOverride(Api.overrideLan);
      expect(Api.baseUrl.contains('tetrapodic-riotous-rosario.ngrok-free.dev'), isTrue,
          reason: 'LAN override should set ngrok URL');

      Api.baseUrl = 'http://example.invalid';
      await Api.init();
      expect(Api.baseUrl.contains('tetrapodic-riotous-rosario.ngrok-free.dev'), isTrue,
          reason: 'LAN override should persist across init');
    });

    test('Auto (or null) clears override and falls back to default', () async {
      await Api.init();
      await Api.setOverride(Api.overrideEmulator);
      expect(Api.baseUrl.contains('tetrapodic-riotous-rosario.ngrok-free.dev'), isTrue);

      // Clear override
      await Api.setOverride(Api.overrideAuto);
      Api.baseUrl = 'http://example.invalid';
      await Api.init();
      expect(Api.baseUrl.contains('tetrapodic-riotous-rosario.ngrok-free.dev'), isTrue,
          reason: 'Auto should remove override and fall back to default');
    });
  });
}
