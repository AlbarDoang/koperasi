import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:tabungan/config/api.dart';
// Use the correct launcher import (wrapper that exports the existing launcer.dart)
import 'package:tabungan/launcer.dart';
import 'package:tabungan/login.dart';
import 'package:tabungan/page/daftar/register1.dart';
import 'package:tabungan/page/daftar/register2.dart';
import 'package:tabungan/page/aktivasi/setpin.dart';

// Tambahkan import untuk dashboard (halaman utama yang baru)
import 'package:tabungan/page/dashboard.dart';
import 'package:tabungan/page/profil.dart';
import 'package:tabungan/page/tabungan.dart';
import 'package:tabungan/page/lainnya.dart';
import 'package:tabungan/page/riwayat.dart';
import 'package:tabungan/page/pinjaman.dart';
import 'package:tabungan/page/ajukan_pinjaman.dart';
import 'package:tabungan/page/bayar_listrik.dart';
import 'package:tabungan/page/isi_pulsa.dart';
import 'package:tabungan/page/isi_kuota.dart';
import 'package:tabungan/page/mulai_menabung.dart';
import 'package:tabungan/page/minta.dart';
import 'package:tabungan/page/notifikasi.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/page/pindai.dart';
import 'package:tabungan/controller/theme_controller.dart';
import 'package:tabungan/page/test_toast.dart'; // Test page
import 'package:tabungan/page/transaction_detail_page.dart';
import 'package:intl/intl.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:tabungan/services/network_test_service.dart';
import 'package:tabungan/utils/app_messenger.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await dotenv.load(fileName: ".env");
  // Initialize Api config (reads persisted debug override if present)
  await Api.init();
  // Initialize locale data required by DateFormat, default to Indonesian
  await initializeDateFormatting('id');
  Intl.defaultLocale = 'id';

  Get.put(CUser());
  Get.put(ThemeController());

  // 🌐 Test network connectivity at startup (debug mode only)
  await NetworkTestService.testBackendConnectivity();

  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    final themeController = Get.find<ThemeController>();
    return GetMaterialApp(
      title: 'Koperasi GAS',
      debugShowCheckedModeBanner: false,
      theme: ThemeController.lightTheme,
      darkTheme: ThemeController.darkTheme,
      themeMode: themeController.isDarkMode.value
          ? ThemeMode.dark
          : ThemeMode.light,
        scaffoldMessengerKey: appMessengerKey,
      home: const LauncherPage(),

      // Semua route dari file lain sudah ditambahkan di sini
      getPages: [
        GetPage(name: '/login', page: () => const LoginPage()),
        GetPage(name: '/register1', page: () => const Register1Page()),
        GetPage(name: '/register2', page: () => const Register2Page()),
        GetPage(name: '/aktivasi', page: () => const SetPinPage()),
        // Backwards-compatible and preferred route name for set PIN
        GetPage(name: '/setpin', page: () => const SetPinPage()),

        // Primary app pages
        GetPage(name: '/dashboard', page: () => Dashboard()),
        GetPage(name: '/profil', page: () => const Profil()),
        GetPage(name: '/tabungan', page: () => const TabunganPage()),
        GetPage(name: '/lainnya', page: () => const LainnyaPage()),
        GetPage(name: '/riwayat', page: () => const RiwayatTransaksiPage()),
        GetPage(
          name: '/riwayat_transaksi',
          page: () => const RiwayatTransaksiPage(),
        ),
        GetPage(name: '/pinjaman', page: () => const PinjamanPage()),
        GetPage(
          name: '/ajukan_pinjaman',
          page: () => const AjukanPinjamanPage(),
        ),
        GetPage(name: '/bayar_listrik', page: () => const BayarListrikPage()),
        GetPage(name: '/isi_pulsa', page: () => const IsiPulsaPage()),
        GetPage(name: '/isi_kuota', page: () => const IsiKuotaPage()),
        GetPage(name: '/mulai_menabung', page: () => const MulaiMenabungPage()),
        GetPage(name: '/minta', page: () => const MintaPage()),
        GetPage(name: '/notifikasi', page: () => const NotifikasiPage()),
        GetPage(name: '/pindai', page: () => const PindaiPage()),
        GetPage(name: '/test-toast', page: () => const TestToastPage()),
      ],
    );
  }
}
