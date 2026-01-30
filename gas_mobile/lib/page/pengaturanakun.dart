// ignore_for_file: library_private_types_in_public_api, unused_import

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/login.dart';
import 'package:tabungan/page/bantuan.dart';
import 'package:tabungan/page/ubahpin.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:tabungan/page/updateprofile.dart';
import 'package:tabungan/page/gantipw.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/controller/theme_controller.dart';
import 'package:tabungan/utils/url_launcher_util.dart';

import 'package:tabungan/utils/custom_toast.dart';

class Profil extends StatefulWidget {
  const Profil({super.key});

  @override
  _ProfilState createState() => _ProfilState();
}

class _ProfilState extends State<Profil> {
  final CUser _cUser = Get.find<CUser>();
  bool _isLogoutPressed = false; // animasi tekan tombol keluar

  @override
  void initState() {
    super.initState();
    getUser();
  }

  Future refreshData() async {
    await Future.delayed(const Duration(seconds: 2));
    getUser();
  }

  void getUser() async {
    User? user = await EventPref.getUser();
    if (user != null) {
      _cUser.setUser(user);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: SafeArea(
        child: Column(
          children: [
            // ✅ HEADER DISAMAKAN DENGAN RIWAYAT TRANSAKSI
            Container(
              height: 110, // sebelumnya 140 → dipendekkan agar sama proporsi
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0xFFFF4C00), Color(0xFFFF6B2C)],
                ),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Positioned(
                    left: 8,
                    top: 8,
                    child: IconButton(
                      icon: const Icon(Icons.arrow_back, color: Colors.white),
                      onPressed: () => Navigator.of(context).maybePop(),
                    ),
                  ),
                  Positioned(
                    top: 16,
                    child: Text(
                      'Profil Pengguna',
                      style: GoogleFonts.roboto(
                        color: Colors.white,
                        fontWeight: FontWeight.w600,
                        fontSize: 18,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // ✅ ISI HALAMAN
            Expanded(
              child: SingleChildScrollView(
                child: Column(
                  children: [
                    // Profile Card
                    Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Container(
                        padding: const EdgeInsets.all(16.0),
                        decoration: BoxDecoration(
                          color: theme.cardColor,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(
                                isDark ? 0.45 : 0.1,
                              ),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Row(
                          children: [
                            CircleAvatar(
                              radius: 40,
                              backgroundImage: NetworkImage(
                                  "https://tabungan.boash.sch.id/assets/images/user.png",
                                ),
                            ),
                            const SizedBox(width: 16),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _cUser.user.nama ?? 'Azmi',
                                  style: GoogleFonts.roboto(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _cUser.user.no_hp ?? '089654334345',
                                  style: GoogleFonts.roboto(
                                    fontSize: 14,
                                    color:
                                        theme.textTheme.bodyMedium?.color
                                            ?.withOpacity(0.85) ??
                                        Colors.grey[600],
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _cUser.user.no_hp ?? '',
                                  style: GoogleFonts.roboto(
                                    fontSize: 14,
                                    color:
                                        theme.textTheme.bodyMedium?.color
                                            ?.withOpacity(0.85) ??
                                        Colors.grey[600],
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),

                    // Info Card
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16.0),
                      child: Container(
                        padding: const EdgeInsets.all(16.0),
                        decoration: BoxDecoration(
                          color: theme.cardColor,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(
                                isDark ? 0.45 : 0.1,
                              ),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          children: [
                            _infoRow(
                              'ID Pengguna',
                              _cUser.user.id ?? '-----',
                            ),
                            _infoRow(
                              'Nomor HP',
                              _cUser.user.no_hp ?? '-----',
                            ),
                            _infoRow(
                              'Alamat',
                              _cUser.user.alamat ?? '-',
                            ),
                            _infoRow(
                              'No Ponsel',
                              _cUser.user.no_hp ?? '089654334345',
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Action Tiles
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16.0),
                      child: Column(
                        children: [
                          _actionTile(Icons.settings, 'Pengaturan Akun', () {
                            Get.to(() => const PengaturanProfil());
                          }),
                          const SizedBox(height: 8),
                          _actionTile(
                            Icons.dark_mode_outlined,
                            'Dark Mode',
                            () {
                              Get.find<ThemeController>().toggleTheme();
                            },
                          ),
                          const SizedBox(height: 8),
                          _actionTile(Icons.help_outline, 'Bantuan', () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const BantuanPage(),
                              ),
                            );
                          }),
                        ],
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Message Section
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          RichText(
                            text: TextSpan(
                              style: GoogleFonts.roboto(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color:
                                    theme.textTheme.bodyLarge?.color ??
                                    Colors.black87,
                              ),
                              children: [
                                const TextSpan(text: 'Pesan kami dari '),
                                WidgetSpan(
                                  alignment: PlaceholderAlignment.middle,
                                  child: Padding(
                                    padding: const EdgeInsets.only(left: 4),
                                    child: Image.asset(
                                      'assets/logo.png',
                                      height: 90,
                                      fit: BoxFit.contain,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Apa pun impianmu, kami siap membantu kamu menjadi ahli dalam mengelola keuangan dan tabungan. Yuk, #Nabung bersama GAS GAS GAS!',
                            style: GoogleFonts.roboto(
                              fontSize: 14,
                              color:
                                  theme.textTheme.bodyMedium?.color
                                      ?.withOpacity(0.85) ??
                                  Colors.grey[700],
                            ),
                            textAlign: TextAlign.left,
                            softWrap: true,
                          ),
                          const SizedBox(height: 8),
                          ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFFFF5C00),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            onPressed: () async {
                              // Ganti dengan package ID aplikasi Anda
                              await URLLauncherUtil.openPlayStore(
                                context,
                                'com.example.tabungan',
                              );
                            },
                            child: const Text(
                              'Beri kami nilai',
                              style: TextStyle(color: Colors.white),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            'Ikuti kami di:',
                            style: GoogleFonts.roboto(
                              fontSize: 13,
                              color:
                                  theme.textTheme.bodySmall?.color?.withOpacity(
                                    0.85,
                                  ) ??
                                  Colors.grey[700],
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                            children: [
                              _socialIcon(
                                'assets/icons/facebook.png',
                                'https://www.facebook.com/gustiglobalgroup',
                              ),
                              _socialIcon(
                                'assets/icons/instagram.png',
                                'https://www.instagram.com/gustiglobalgroup',
                              ),
                              _socialIcon(
                                'assets/icons/twitter.png',
                                'https://x.com/gustiglobalgroup',
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Logout Button
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16.0),
                      child: GestureDetector(
                        onTapDown: (_) =>
                            setState(() => _isLogoutPressed = true),
                        onTapCancel: () =>
                            setState(() => _isLogoutPressed = false),
                        onTapUp: (_) =>
                            setState(() => _isLogoutPressed = false),
                        onTap: () {
                          showDialog(
                            context: context,
                            builder: (BuildContext context) {
                              return AlertDialog(
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                ),
                                content: const Text(
                                  "Apakah yakin ingin keluar?",
                                  style: TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                                actionsPadding: const EdgeInsets.only(
                                  bottom: 8,
                                  right: 8,
                                ),
                                actions: [
                                  TextButton(
                                    onPressed: () =>
                                        Navigator.of(context).pop(),
                                    child: const Text(
                                      "Tidak",
                                      style: TextStyle(
                                        color: Colors.grey,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                  ElevatedButton(
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: const Color(0xFFFF5C00),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                    ),
                                    onPressed: () {
                                      Navigator.of(context).pop();
                                      EventPref.clear();
                                      Get.offAll(const LoginPage());
                                    },
                                    child: const Text(
                                      "Ya",
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                ],
                              );
                            },
                          );
                        },
                        child: AnimatedScale(
                          duration: const Duration(milliseconds: 120),
                          curve: Curves.easeOut,
                          scale: _isLogoutPressed ? 0.95 : 1.0,
                          child: Container(
                            decoration: BoxDecoration(
                              color: const Color(0xFFFF5C00),
                              borderRadius: BorderRadius.circular(28),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.25),
                                  blurRadius: 12,
                                  offset: const Offset(0, 6),
                                ),
                              ],
                            ),
                            child: const Padding(
                              padding: EdgeInsets.symmetric(vertical: 14),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Text(
                                    'Keluar',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 16,
                                    ),
                                  ),
                                  SizedBox(width: 8),
                                  Icon(
                                    Icons.arrow_forward_rounded,
                                    color: Colors.white,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            flex: 3,
            child: Text(
              label,
              style: GoogleFonts.roboto(
                fontSize: 12,
                color:
                    Theme.of(
                      context,
                    ).textTheme.bodySmall?.color?.withOpacity(0.75) ??
                    Colors.grey[700],
              ),
            ),
          ),
          Expanded(
            flex: 5,
            child: Text(
              value,
              style: GoogleFonts.roboto(
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _actionTile(IconData icon, String title, VoidCallback onTap) {
    return Ink(
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(12),
        ),
        child: ListTile(
          leading: Icon(icon, color: Theme.of(context).iconTheme.color),
          title: Text(
            title,
            style: GoogleFonts.roboto(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: Theme.of(context).textTheme.bodyLarge?.color,
            ),
          ),
          trailing: const Icon(Icons.navigate_next),
          onTap: onTap,
        ),
      ),
    );
  }

  Widget _socialIcon(String assetPath, String url) {
    return GestureDetector(
      onTap: () async {
        await URLLauncherUtil.openURL(context, url);
      },
      child: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(
                Theme.of(context).brightness == Brightness.dark ? 0.25 : 0.04,
              ),
              blurRadius: 6,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Center(
          child: Image.asset(
            assetPath,
            width: 28,
            height: 28,
            fit: BoxFit.contain,
            errorBuilder: (c, e, s) => const SizedBox.shrink(),
          ),
        ),
      ),
    );
  }
}

class PengaturanProfil extends StatelessWidget {
  const PengaturanProfil({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: OrangeHeader(title: "Pengaturan Akun"),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          children: [
            ListTile(
              leading: const Icon(Icons.lock_outline),
              title: const Text('Ubah PIN Keamanan'),
              onTap: () => Get.to(() => const UbahPinPage()),
            ),
            const SizedBox(height: 8),
            ListTile(
              leading: const Icon(Icons.password),
              title: const Text('Ganti Password'),
              onTap: () => Get.to(() => GantiPwPage()),
            ),
            const SizedBox(height: 8),
            ListTile(
              leading: const Icon(Icons.edit),
              title: const Text('Edit Profil'),
              onTap: () async {
                // Navigate to edit profile and wait for it to return
                await Get.to(() => EditProfilePage());
                // After returning, refresh the profile data
                // This will be caught by the profile page's didChangeAppLifecycleState
              },
            ),
            const SizedBox(height: 8),
            ListTile(
              leading: const Icon(Icons.delete_forever, color: Colors.red),
              title: const Text('Hapus Akun'),
              onTap: () {
                showDialog(
                  context: context,
                  builder: (BuildContext context) {
                    return AlertDialog(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      title: const Text(
                        "Konfirmasi",
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 18,
                        ),
                      ),
                      content: const Text(
                        "Apakah kamu yakin ingin menghapus akun ini?",
                        style: TextStyle(fontSize: 14),
                      ),
                      actions: [
                        TextButton(
                          onPressed: () {
                            Navigator.of(context).pop();
                          },
                          child: const Text(
                            "Batal",
                            style: TextStyle(color: Colors.grey),
                          ),
                        ),
                        ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFFF5F0A),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          onPressed: () async {
                            Navigator.of(context).pop();
                            // Perform deletion: show loading dialog while calling API
                            final user = await EventPref.getUser();
                            if (user == null || user.id == null) {
                              CustomToast.error(context, 'User tidak ditemukan');
                              return;
                            }

                            // show loading
                            Get.dialog(
                              const Center(child: CircularProgressIndicator()),
                              barrierDismissible: false,
                            );

                            final ok = await EventDB.deleteUser(user.id!);

                            // close loading
                            if (Get.isDialogOpen ?? false) Get.back();

                            if (ok) {
                              // clear local data and go to login
                              await EventPref.clear();
                              Get.offAll(() => const LoginPage());
                            }
                          },
                          child: const Text(
                            "Hapus",
                            style: TextStyle(color: Colors.white),
                          ),
                        ),
                      ],
                    );
                  },
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
