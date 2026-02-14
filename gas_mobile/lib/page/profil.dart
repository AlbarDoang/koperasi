// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/page/pengaturanakun.dart';
import 'package:tabungan/page/bantuan.dart';
import 'package:tabungan/login.dart';
import 'package:tabungan/page/orange_header.dart'; // 🔥 DITAMBAHKAN
import 'package:tabungan/utils/url_launcher_util.dart';

class Profil extends StatefulWidget {
  const Profil({super.key});

  @override
  _ProfilState createState() => _ProfilState();
}

class _ProfilState extends State<Profil> with WidgetsBindingObserver {
  final CUser _cUser = Get.find<CUser>();
  bool _isRefreshingPhoto = false; // prevent multiple refresh calls

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    getUser();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Reload user data setiap kali app/page resumed
      getUser();
    }
  }

  void getUser() async {
    // Ambil ID user dari SharedPreferences
    final localUser = await EventPref.getUser();
    
    if (localUser != null && localUser.id != null) {
      // Load profile lengkap dari database (termasuk foto dari server)
      try {
        User? profileFromDB = await EventDB.getProfilLengkap(localUser.id!);
        if (profileFromDB != null) {
            _cUser.setUser(profileFromDB);
            try { await EventPref.saveUser(profileFromDB); } catch (_) {}
        } else {
          // Jika gagal load dari DB, gunakan data lokal
          _cUser.setUser(localUser);
        }
      } catch (e) {
        // Jika error, gunakan data lokal dari SharedPreferences
        _cUser.setUser(localUser);
      }
    }
  }

  // Refresh profile data - dapat dipanggil dari mana saja
  void refreshProfile() {
    getUser();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,

      // 🔥 AppBar diganti pakai OrangeHeader
      appBar: OrangeHeader(
          title: "Profil",
        onBackPressed: () => Navigator.of(context).maybePop(),
      ),

      body: SingleChildScrollView(
        child: Column(
          children: [
            // Profile Card
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: GetBuilder<CUser>(
                builder: (cUser) => Container(
                  padding: const EdgeInsets.all(16.0),
                  decoration: BoxDecoration(
                    color: theme.cardColor,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.1),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      SizedBox(
                        width: 80,
                        height: 80,
                        child: ClipOval(
                          child: Builder(builder: (context) {
                            final foto = cUser.user.foto;
                            if (foto != null && foto.isNotEmpty) {
                              final ts = cUser.user.fotoUpdatedAt ?? (DateTime.now().millisecondsSinceEpoch ~/ 1000);
                              final imageUrl = (foto.contains('?') ? '$foto&t=$ts' : '$foto?t=$ts');
                              return Image.network(
                                imageUrl,
                                key: ValueKey(imageUrl),
                                fit: BoxFit.cover,
                                gaplessPlayback: true,
                                headers: const {'Accept': 'image/*'},
                                errorBuilder: (c, e, s) {
                                  // URL might be expired, trigger background refresh (once)
                                  if (!_isRefreshingPhoto) {
                                    _isRefreshingPhoto = true;
                                    WidgetsBinding.instance.addPostFrameCallback((_) {
                                      getUser();
                                      Future.delayed(const Duration(seconds: 5), () {
                                        _isRefreshingPhoto = false;
                                      });
                                    });
                                  }
                                  return Container(
                                    color: Colors.grey[300],
                                    child: const Icon(Icons.person, size: 40, color: Colors.grey),
                                  );
                                },
                              );
                            }
                            return Container(
                              color: Colors.grey[300],
                              child: const Icon(Icons.person, size: 40, color: Colors.grey),
                            );
                          }),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            cUser.user.nama ?? 'Azmi',
                            style: GoogleFonts.roboto(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            cUser.user.no_hp ?? '089654334345',
                            style: GoogleFonts.roboto(
                              fontSize: 14,
                              color:
                                  theme.textTheme.bodyMedium?.color?.withOpacity(
                                    0.85,
                                  ) ??
                                  Colors.grey[600],
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            cUser.user.created_at != null ? 'Terdaftar: ${cUser.user.created_at}' : '',
                            style: GoogleFonts.roboto(
                              fontSize: 14,
                              color: Colors.grey[600],
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Info Card
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16.0),
              child: GetBuilder<CUser>(
                builder: (cUser) => Container(
                  padding: const EdgeInsets.all(16.0),
                  decoration: BoxDecoration(
                    color: theme.cardColor,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.1),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      _infoRow(
                        'ID Pengguna',
                        cUser.user.id ?? '-----',
                      ),
                      _infoRow(
                        'Tanggal Lahir',
                        cUser.user.tanggal_lahir ?? '-----',
                      ),
                      _infoRow('Alamat', cUser.user.alamat ?? '-----'),
                      _infoRow('No Ponsel', cUser.user.no_hp ?? '08xxxxxxxxxx'),
                    ],
                  ),
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
                  _actionTile(Icons.help_outline, 'Bantuan', () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const BantuanPage()),
                    );
                  }),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Pesan Kami
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
                        color: Colors.black87,
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
                      color: Colors.grey[700],
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
                    onPressed: () {
                      URLLauncherUtil.openPlayStore(
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
                      color: Colors.grey[700],
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      _socialIcon(
                        'assets/icons/facebook.png',
                        'https://www.facebook.com/share/1BctZBEpgc/',
                      ),
                      _socialIcon(
                        'assets/icons/instagram.png',
                        'https://www.instagram.com/simtekofficial?igsh=MTgxNHhzbjR0Z2ptcA==',
                      ),
                      _socialIcon(
                        'assets/icons/linkedin.png',
                        'https://www.linkedin.com/company/simtekin/',
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Logout Button (WITH POPUP)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16.0),
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
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(28),

                    // POPUP LOGOUT
                    onTap: () {
                      showDialog(
                        context: context,
                        barrierDismissible: true,
                        builder: (context) {
                          return Center(
                            child: Material(
                              color: Colors.transparent,
                              child: Container(
                                width: 300,
                                padding: const EdgeInsets.all(20),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(20),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withOpacity(0.25),
                                      blurRadius: 15,
                                      offset: const Offset(0, 6),
                                    ),
                                  ],
                                ),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Text(
                                      "Konfirmasi",
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 18,
                                        color: Colors.black87,
                                      ),
                                    ),
                                    const SizedBox(height: 10),
                                    const Text(
                                      "Apakah kamu yakin ingin keluar dari akun ini?",
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        fontSize: 14,
                                        color: Colors.black87,
                                      ),
                                    ),
                                    const SizedBox(height: 20),
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        // BATAl
                                        Expanded(
                                          child: ElevatedButton(
                                            onPressed: () =>
                                                Navigator.pop(context),
                                            style: ElevatedButton.styleFrom(
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                    vertical: 10,
                                                  ),
                                              backgroundColor: Colors.grey[200],
                                              foregroundColor: Colors.black87,
                                              shape: RoundedRectangleBorder(
                                                borderRadius:
                                                    BorderRadius.circular(10),
                                              ),
                                            ),
                                            child: const Text("Batal"),
                                          ),
                                        ),
                                        const SizedBox(width: 12),

                                        // KELUAR
                                        Expanded(
                                          child: ElevatedButton(
                                            onPressed: () {
                                              EventPref.clear();
                                              Get.offAll(() => LoginPage());
                                            },
                                            style: ElevatedButton.styleFrom(
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                    vertical: 10,
                                                  ),
                                              backgroundColor: const Color(
                                                0xFFFF5C00,
                                              ),
                                              foregroundColor: Colors.white,
                                              shape: RoundedRectangleBorder(
                                                borderRadius:
                                                    BorderRadius.circular(10),
                                              ),
                                            ),
                                            child: const Text("Keluar"),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      );
                    },

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
    );
  }

  // Widgets Section
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
                color: Theme.of(context).textTheme.bodyLarge?.color,
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
      onTap: () {
        URLLauncherUtil.openURL(context, url);
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
