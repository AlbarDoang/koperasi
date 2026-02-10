// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter/services.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/page/news_detail.dart' as detail;
import 'package:tabungan/page/transfer_page.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import 'dart:convert';
import 'package:intl/intl.dart';
import 'package:tabungan/utils/currency_format.dart';

import 'package:tabungan/widget/bottom_navbar_widget.dart' as navbar;
import 'package:tabungan/controller/notifikasi_helper.dart';
import 'package:tabungan/services/notification_service.dart';

class Dashboard extends StatefulWidget {
  final String? bannerMessage;

  const Dashboard({super.key, this.bannerMessage});

  @override
  _DashboardState createState() => _DashboardState();
}

class _DashboardState extends State<Dashboard> {
  // Use the CUser instance initialized in main.dart
  final CUser _cUser = Get.find<CUser>();
  bool _isSaldoVisible = false;
  bool _hasUnreadMessages = false; // State untuk notifikasi pesan (badge removed)
  bool _bannerShown = false;
  int _currentIndex = 2; // Track bottom nav selected index

  void getUser() async {
    User? user = await EventPref.getUser();
    if (user != null) {
      _cUser.setUser(user);
      try {
        final fresh = await EventDB.getProfilLengkap(user.id ?? '');
        if (fresh != null) {
          _cUser.setUser(fresh);
          // Already saved by getProfilLengkap, but ensure the pref is set
          await EventPref.saveUser(fresh);
        }
        else {
          // If profile fetch failed, try refreshing only the saldo so UI doesn't show stale value
          EventDB.refreshSaldoForCurrentUser(idPengguna: user.id);
        }

        // Security: verify PIN/tabungan status and force set PIN page if needed
        try {
          final check = await EventDB.checkPin(id: user.id?.toString());
          if (check != null && check['needs_set_pin'] == true) {
            // Force navigation to Set PIN page
            if (mounted) {
              Get.offAllNamed('/setpin');
              return;
            }
          }
        } catch (_) {}

      } catch (_) {}
    }
  }

  void toggleSaldoVisibility() {
    setState(() {
      _isSaldoVisible = !_isSaldoVisible;
    });
  }

  Timer? _notifTimer;

  @override
  void initState() {
    super.initState();
    getUser();
    _loadRecentTopups();
    _updateUnreadNotifications();
    _initializeNotifications();

    // Start periodic polling for notifications while dashboard is active.
    // Polling interval kept modest (15s) to provide near real-time updates.
    _notifTimer = Timer.periodic(const Duration(seconds: 15), (_) async {
      await _initializeNotifications();
      await _updateUnreadNotifications();
    });

    // If a banner message was passed when navigating to Dashboard,
    // show it once after the first frame and mark it as shown so it
    // won't reappear on subsequent rebuilds or when returning to
    // the Dashboard instance.
    if (widget.bannerMessage != null && widget.bannerMessage!.isNotEmpty) {
      final msg = widget.bannerMessage!;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_bannerShown && mounted) {
          NotificationService.showSuccess(msg);
          setState(() {
            _bannerShown = true;
          });
        }
      });
    }
  }

  @override
  void dispose() {
    _notifTimer?.cancel();
    super.dispose();
  }

  Future<void> _initializeNotifications() async {
    await NotifikasiHelper.initializeNotifications();
  }

  Future<void> _updateUnreadNotifications() async {
    final unreadCount = await NotifikasiHelper.getUnreadCount();
    setState(() {
      _hasUnreadMessages = unreadCount > 0;
    });
  }

  List<Map<String, dynamic>> _recentTopups = [];

  Future<void> _loadRecentTopups() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final txns = prefs.getString('transactions');
      if (txns == null) return;
      final parsed = jsonDecode(txns) as List;
      final list = <Map<String, dynamic>>[];
      for (var e in parsed) {
        final m = Map<String, dynamic>.from(e);
        if (m['type'] == 'topup') list.add(m);
      }
      // sort desc
      list.sort(
        (a, b) => (b['created_at'] ?? b['id'].toString()).toString().compareTo(
          (a['created_at'] ?? a['id'].toString()).toString(),
        ),
      );
      setState(() {
        _recentTopups = list.take(3).toList();
      });
    } catch (_) {}
  }

  Future<void> refreshData() async {
    getUser();
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: Column(
        children: [
          // Existing dashboard content
          Expanded(
            child: RefreshIndicator(
              onRefresh: refreshData,
              child: ListView(
                padding: EdgeInsets.zero,
                physics: const ClampingScrollPhysics(),
                children: <Widget>[
                  buildOrangeHeader(context),
                  buildWhiteMenuSection(context), // Menu putih baru
                  buildInfoCardsSection(context),
                  buildMenabungSection(context),
                  buildRecentTopupsSection(context),
                  buildDaftarAkunSection(context),
                  buildMenabungMudahSection(context),
                  buildKeamananSection(context),
                  buildTransferSaldoSection(context),
                  buildFooterSection(context),
                ],
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: navbar.BottomNavBarWidget(
        currentIndex: _currentIndex,
        onItemTapped: (index) {
          // Update local selected index so the nav animation moves
          setState(() {
            _currentIndex = index;
          });

          // Map bottom nav indices to named routes
          // 0: Lainnya, 1: Riwayat, 2: Dashboard, 3: Tabungan, 4: Profil
          switch (index) {
            case 0:
              // Navigate and reset to dashboard when returning
              Get.toNamed('/lainnya')?.then((_) {
                setState(() {
                  _currentIndex = 2;
                });
              });
              break;
            case 1:
              Get.toNamed('/riwayat_transaksi')?.then((_) {
                setState(() {
                  _currentIndex = 2;
                });
              });
              break;
            case 2:
              // Already on dashboard - ensure state reflects that
              setState(() {
                _currentIndex = 2;
              });
              break;
            case 3:
              Get.toNamed('/tabungan')?.then((_) {
                setState(() {
                  _currentIndex = 2;
                });
              });
              break;
            case 4:
              Get.toNamed('/profil')?.then((_) {
                // When returning from profile, make sure navbar selects Dashboard
                setState(() {
                  _currentIndex = 2;
                });
              });
              break;
            default:
              break;
          }
        },
      ),
    );
  }

  // Orange header with icons
  Widget buildOrangeHeader(BuildContext context) {
    final double topPad = MediaQuery.of(context).padding.top;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
      ),
      child: Container(
        padding: EdgeInsets.fromLTRB(18, topPad + 16, 18, 36),
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFFF4C00), Color(0xFFFF6B2C)],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top app bar row: logo, RP., visibility icon, mail
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                // Logo kiri dengan animasi sederhana - gunakan logo yang ada di repo
                _SimplePulseLogo(
                  child: Image.asset(
                    'assets/logo gas warna putih.png',
                    height: 64,
                    fit: BoxFit.contain,
                    filterQuality: FilterQuality.high,
                    isAntiAlias: true,
                    // Keep an errorBuilder just in case, fallback to main logo
                    errorBuilder: (c, e, s) {
                      return Image.asset(
                        'assets/logo.png',
                        height: 58,
                        fit: BoxFit.contain,
                        filterQuality: FilterQuality.high,
                      );
                    },
                  ),
                ),
                const SizedBox(width: 14),
                // Removed the literal "RP." label as it's redundant with the currency symbol
                // Saldo display (either hidden pill or numeric amount)
                _buildSaldoDisplay(),
                const Spacer(),
                // Mail icon dengan badge notifikasi
                _buildMailIconWithBadge(),
              ],
            ),
            const SizedBox(height: 36),
            // 4 main action icons dengan teks
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                buildTopAssetIcon('assets/Donate.png', 'Mulai\nNabung', 0),
                buildTopAssetIcon('assets/Exchange.png', 'Transfer', 1),
                buildTopAssetIcon('assets/Request Money.png', 'Minta', 2),
                buildTopIcon(Icons.qr_code_2, 'Pindai', 3),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // White menu section dengan 4 icon menu (Isi Pulsa, Isi Kuota, Listrik, Pinjaman)
  Widget buildWhiteMenuSection(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    return Transform.translate(
      offset: const Offset(0, -24), // Overlap ke atas header orange
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 20),
        padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 12),
        decoration: BoxDecoration(
          color: theme.cardColor,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(isDark ? 0.45 : 0.08),
              blurRadius: 20,
              offset: const Offset(0, 4),
              spreadRadius: 0,
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _buildWhiteMenuItem(
              icon: Icons.phone_android,
              label: 'Isi Pulsa',
              color: const Color(0xFFFF4C00),
            ),
            _buildWhiteMenuItem(
              icon: Icons.signal_cellular_alt,
              label: 'Isi Kuota',
              color: const Color(0xFFFF4C00),
            ),
            _buildWhiteMenuItem(
              icon: Icons.lightbulb_outline,
              label: 'Listrik',
              color: const Color(0xFFFF4C00),
            ),
            _buildWhiteMenuItemWithImage(
              imagePath: 'assets/pinjaman1.png',
              label: 'Pinjaman',
              color: const Color(0xFFFF4C00),
            ),
          ],
        ),
      ),
    );
  }

  // Widget untuk single menu item di white section
  Widget _buildWhiteMenuItem({
    required IconData icon,
    required String label,
    required Color color,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: () {
          if (label == 'Transfer') {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (context) => const TransferPage()),
            );
          } else if (label == 'Listrik') {
            NotificationService.showInfo('Fitur Isi Listrik belum tersedia');
          } else if (label == 'Isi Pulsa') {
            NotificationService.showInfo('Fitur Isi Pulsa belum tersedia');
          } else if (label == 'Isi Kuota') {
            NotificationService.showInfo('Fitur Isi Kuota belum tersedia');
          }
        },
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: color, size: 26),
            ),
            const SizedBox(height: 10),
            Text(
              label,
              style: GoogleFonts.roboto(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color:
                    Theme.of(context).textTheme.bodySmall?.color ??
                    const Color(0xFF333333),
              ),
              textAlign: TextAlign.center,
              maxLines: 1,
            ),
          ],
        ),
      ),
    );
  }

  // Widget untuk menu item dengan custom image
  Widget _buildWhiteMenuItemWithImage({
    required String imagePath,
    required String label,
    required Color color,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: () {
          // Navigate to the new Pinjaman page
          Get.toNamed('/pinjaman');
        },
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // Icon handshake untuk pinjaman (simbol kesepakatan/kerjasama)
                  Icon(Icons.handshake_outlined, color: color, size: 28),
                  // Badge "Rp" kecil di pojok kanan atas
                  Positioned(
                    top: 8,
                    right: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 3,
                        vertical: 1,
                      ),
                      decoration: BoxDecoration(
                        color: color,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        'Rp',
                        style: GoogleFonts.roboto(
                          fontSize: 7,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                          height: 1.2,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            Text(
              label,
              style: GoogleFonts.roboto(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color: const Color(0xFF333333),
              ),
              textAlign: TextAlign.center,
              maxLines: 1,
            ),
          ],
        ),
      ),
    );
  }

  // Widget untuk menampilkan saldo atau placeholder saat di-hide (style DANA)
  Widget _buildSaldoDisplay() {
    return Obx(() {
      final saldo = _cUser.user.saldo ?? 0.0;
      final saldoFormatted = CurrencyFormat.toIdr(saldo.toInt());

      // Visible: show numeric value dari user data, Hidden: show dots
      return Row(
        children: [
          Text(
            _isSaldoVisible ? saldoFormatted : '••••••••',
            style: GoogleFonts.roboto(
              color: Colors.white,
              fontWeight: FontWeight.w700,
              fontSize: 18,
              letterSpacing: _isSaldoVisible ? 0.5 : 2.0,
            ),
          ),
          const SizedBox(width: 10),
          GestureDetector(
            onTap: toggleSaldoVisibility,
            child: Icon(
              _isSaldoVisible
                  ? Icons.visibility_outlined
                  : Icons.visibility_off_outlined,
              color: Colors.white,
              size: 22,
            ),
          ),
        ],
      );
    });
  }

  // Notifications icon dengan badge notifikasi merah
  Widget _buildMailIconWithBadge() {
    return GestureDetector(
      onTap: () {
        Get.toNamed('/notifikasi');
      },
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          // Mail icon
          Container(
            padding: const EdgeInsets.all(6),
            child: const Icon(
              Icons.notifications,
              color: Colors.white,
              size: 26,
            ),
          ),
          // Badge merah jika ada pesan belum dibaca
          if (_hasUnreadMessages)
            Positioned(
              right: 4,
              top: 4,
              child: Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: Colors.red,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 1.5),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget buildTopAssetIcon(String assetPath, String label, int index) {
    return TweenAnimationBuilder(
      duration: Duration(milliseconds: 400 + (index * 100)),
      tween: Tween<double>(begin: 0, end: 1),
      curve: Curves.easeOutBack,
      builder: (context, double value, child) {
        return Transform.translate(
          offset: Offset(0, 20 * (1 - value)),
          child: Opacity(
            opacity: value.clamp(0.0, 1.0),
            child: _AnimatedIconButton(
              onTap: () {
                if (label.contains('Mulai')) {
                  // Navigate to Mulai Menabung (top-up) page
                  Get.toNamed('/mulai_menabung');
                } else if (label == 'Transfer') {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const TransferPage(),
                    ),
                  );
                } else if (label == 'Minta') {
                  // Navigate to Minta (QR request) page
                  Get.toNamed('/minta');
                } else if (label == 'Pindai') {
                  // Navigate to Pindai (QR scanner) page
                  Get.toNamed('/pindai');
                }
                // Keep other icons' behavior unchanged
              },
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Icon tanpa shadow - clean look
                  Image.asset(
                    assetPath,
                    width: 52,
                    height: 52,
                    fit: BoxFit.contain,
                    color: Colors.white,
                    errorBuilder: (c, e, s) => const Icon(
                      Icons.help_outline,
                      color: Colors.white,
                      size: 52,
                    ),
                  ),
                  const SizedBox(height: 12),
                  // Label text dengan shadow
                  Text(
                    label,
                    textAlign: TextAlign.center,
                    style: GoogleFonts.roboto(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      height: 1.1,
                      shadows: [
                        Shadow(
                          color: Colors.black.withOpacity(0.2),
                          offset: const Offset(0, 1),
                          blurRadius: 2,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
  
  Widget buildTopIcon(IconData iconData, String label, int index) {
    return TweenAnimationBuilder(
      duration: Duration(milliseconds: 400 + (index * 100)),
      tween: Tween<double>(begin: 0, end: 1),
      curve: Curves.easeOutBack,
      builder: (context, double value, child) {
        return Transform.translate(
          offset: Offset(0, 20 * (1 - value)),
          child: Opacity(
            opacity: value.clamp(0.0, 1.0),
            child: _AnimatedIconButton(
              onTap: () {
                if (label.contains('Mulai')) {
                  Get.toNamed('/mulai_menabung');
                } else if (label == 'Transfer') {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const TransferPage(),
                    ),
                  );
                } else if (label == 'Minta') {
                  Get.toNamed('/minta');
                } else if (label == 'Pindai') {
                  Get.toNamed('/pindai');
                }
              },
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(iconData, size: 52, color: Colors.white),
                  const SizedBox(height: 12),
                  Text(
                    label,
                    textAlign: TextAlign.center,
                    style: GoogleFonts.roboto(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      height: 1.1,
                      shadows: [
                        Shadow(
                          color: Colors.black.withOpacity(0.2),
                          offset: const Offset(0, 1),
                          blurRadius: 2,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
  
  // Info cards section (slide1, slide2) - Horizontal Scrollable
  Widget buildInfoCardsSection(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center, // Changed to center
        children: [
          // Title "Info" - Centered
          Text(
            'Info',
            style: GoogleFonts.poppins(
              fontSize: 24,
              fontWeight: FontWeight.bold,
              color: const Color(0xFF333333),
            ),
          ),
          const SizedBox(height: 20),
          // Scrollable cards
          SizedBox(
            height: 380,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              physics: const BouncingScrollPhysics(),
              children: [
                buildInfoCard(
                  'assets/slide1.png',
                  'Aplikasi Pertama Tabungan Gusti Artha Sejahtera',
                  '17 jam yang lalu',
                ),
                const SizedBox(width: 16),
                buildInfoCard(
                  'assets/slide2.png',
                  'Aplikasi Pertama Tabungan Gusti Artha Sejahtera',
                  '17 jam yang lalu',
                ),
                const SizedBox(width: 16),
                buildInfoCard(
                  'assets/slide3.png',
                  'Info terbaru dari Gusti Artha Sejahtera',
                  '1 hari yang lalu',
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget buildInfoCard(String imagePath, String title, String timeAgo) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      width: 320,
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(isDark ? 0.45 : 0.08),
            blurRadius: 20,
            offset: const Offset(0, 6),
            spreadRadius: 0,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Image section
          Stack(
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(20),
                ),
                child: Image.asset(
                  imagePath,
                  height: 200,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (c, e, s) => Container(
                    height: 200,
                    color: const Color(0xFFF0F0F0),
                    child: Icon(
                      Icons.image,
                      size: 60,
                      color: theme.iconTheme.color ?? Colors.grey,
                    ),
                  ),
                ),
              ),
              // Menu button (3 dots)
              Positioned(
                top: 12,
                right: 12,
                child: Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.1),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.more_vert,
                    size: 20,
                    color: Color(0xFF333333),
                  ),
                ),
              ),
            ],
          ),
          // Content section
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title
                  Text(
                    title,
                    style: GoogleFonts.poppins(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color:
                          theme.textTheme.bodyLarge?.color ??
                          const Color(0xFF333333),
                      height: 1.4,
                    ),
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const Spacer(),
                  // Bottom section with time and button
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      // Time ago
                      Text(
                        timeAgo,
                        style: GoogleFonts.roboto(
                          fontSize: 12,
                          color:
                              theme.textTheme.bodySmall?.color ??
                              const Color(0xFF999999),
                          fontWeight: FontWeight.w400,
                        ),
                      ),
                      // Lihat button
                      GestureDetector(
                        onTap: () {
                          // Navigate ke halaman detail berita
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => detail.NewsDetailPage(
                                imagePath: imagePath,
                                title: title,
                                timeAgo: timeAgo,
                              ),
                            ),
                          );
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 20,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFF007BFF),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'Lihat',
                                style: GoogleFonts.roboto(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.white,
                                ),
                              ),
                              const SizedBox(width: 4),
                              const Icon(
                                Icons.arrow_forward,
                                size: 16,
                                color: Colors.white,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // Menabung Di Gusti Artha Sejahtera section (orange banner with cards)
  Widget buildMenabungSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFFF4C00), Color(0xFFFF6B2C)],
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFFF4C00).withValues(alpha: 0.3),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header dengan logo dan title
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Title di kiri
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Menabung Di',
                      style: GoogleFonts.poppins(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                        height: 1.2,
                      ),
                    ),
                    Text(
                      'Gusti Artha Sejahtera',
                      style: GoogleFonts.poppins(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                        height: 1.2,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              // Logo di kanan
              Image.asset(
                'assets/logo gas warna putih.png',
                height: 100,
                fit: BoxFit.contain,
                errorBuilder: (c, e, s) => const SizedBox(),
              ),
            ],
          ),
          const SizedBox(height: 16),
          // Lorem ipsum text
          Text(
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
            style: GoogleFonts.roboto(
              fontSize: 13,
              color: Colors.white.withValues(alpha: 0.95),
              height: 1.6,
            ),
          ),
          const SizedBox(height: 28),
          // 3 Feature cards dalam row dengan spacing
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                flex: 1,
                child: buildMenabungCard(
                  'assets/slide1.png',
                  'Simpan Uang\nMudah',
                ),
              ),
              const SizedBox(width: 12),
              Flexible(
                flex: 1,
                child: buildMenabungCard(
                  'assets/slide2.png',
                  'Keuntungan\nLebih',
                ),
              ),
              const SizedBox(width: 12),
              Flexible(
                flex: 1,
                child: buildMenabungCard(
                  'assets/slide3.png',
                  'Transfer\nCepat',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // Card untuk feature menabung (white card with illustration image)
  Widget buildMenabungCard(String imagePath, String label) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(isDark ? 0.45 : 0.12),
            blurRadius: 15,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Illustration image dengan white border (tanpa background orange)
          Container(
            margin: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            decoration: BoxDecoration(
              color: theme.cardColor,
              border: Border.all(color: theme.cardColor, width: 8),
              borderRadius: BorderRadius.circular(16),
            ),
            child: AspectRatio(
              aspectRatio: 1,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.asset(
                  imagePath,
                  fit: BoxFit.cover,
                  errorBuilder: (c, e, s) => Container(
                    color: const Color(0xFFF0F0F0),
                    child: const Icon(
                      Icons.image,
                      color: Colors.grey,
                      size: 50,
                    ),
                  ),
                ),
              ),
            ),
          ),
          // Label text
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 4, 8, 16),
            child: Text(
              label,
              textAlign: TextAlign.center,
              style: GoogleFonts.poppins(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color:
                    theme.textTheme.bodyLarge?.color ?? const Color(0xFF333333),
                height: 1.2,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }

  // Daftar Akun Koperasi GAS section (text kiri, icon kanan)
  Widget buildDaftarAkunSection(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Text di kiri
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Daftar Akun Koperasi GAS',
                  style: GoogleFonts.poppins(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF333333),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Membuat akun tabungan dapat melalui Website atau Aplikasi Tabungan Koperasi Gusti Artha Sejahtera',
                  style: GoogleFonts.roboto(
                    fontSize: 13,
                    color: const Color(0xFF666666),
                    height: 1.6,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 20),
          // Icon hexagonal di kanan (clean image tanpa background)
          Container(
            width: 120,
            height: 120,
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: theme.cardColor,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(
                    theme.brightness == Brightness.dark ? 0.45 : 0.08,
                  ),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.asset(
                'assets/slide1.png',
                fit: BoxFit.cover,
                errorBuilder: (c, e, s) => Container(
                  color: const Color(0xFFF0F0F0),
                  child: const Icon(
                    Icons.home,
                    color: Color(0xFFFF4C00),
                    size: 50,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // Menabung Menjadi Mudah section (icon kiri, text kanan)
  Widget buildMenabungMudahSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Icon hexagonal di kiri (clean image tanpa background)
          Container(
            width: 120,
            height: 120,
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.asset(
                'assets/slide2.png',
                fit: BoxFit.cover,
                errorBuilder: (c, e, s) => Container(
                  color: const Color(0xFFF0F0F0),
                  child: const Icon(
                    Icons.credit_card,
                    color: Color(0xFFFF4C00),
                    size: 50,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 20),
          // Text di kanan
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Menabung Menjadi Mudah',
                  style: GoogleFonts.poppins(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF333333),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Menabung lebih mudah dengan mandangangi Ruangan Tabungan Koperasi di PT. Gusti Business Distrik untuk bertemu dengan Petugas.',
                  style: GoogleFonts.roboto(
                    fontSize: 13,
                    color: const Color(0xFF666666),
                    height: 1.6,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Keamanan Tabungan section (text kiri, icon kanan)
  Widget buildKeamananSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Text di kiri
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Keamanan Tabungan',
                  style: GoogleFonts.poppins(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF333333),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Uang Tabungan anda akan tersimpan aman di Tabungan Koperasi jadi tidak perlu khawatir untuk menabung.',
                  style: GoogleFonts.roboto(
                    fontSize: 13,
                    color: const Color(0xFF666666),
                    height: 1.6,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 20),
          // Icon hexagonal di kanan (clean image tanpa background)
          Container(
            width: 120,
            height: 120,
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.asset(
                'assets/slide3.png',
                fit: BoxFit.cover,
                errorBuilder: (c, e, s) => Container(
                  color: const Color(0xFFF0F0F0),
                  child: const Icon(
                    Icons.security,
                    color: Color(0xFFFF4C00),
                    size: 50,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // Bisa Transfer Saldo section (icon kiri, text kanan)
  Widget buildTransferSaldoSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Icon hexagonal di kiri (clean image tanpa background)
          Container(
            width: 120,
            height: 120,
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.asset(
                'assets/slide4.png',
                fit: BoxFit.cover,
                errorBuilder: (c, e, s) => Container(
                  color: const Color(0xFFF0F0F0),
                  child: const Icon(
                    Icons.swap_horiz,
                    color: Color(0xFFFF4C00),
                    size: 50,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 20),
          // Text di kanan
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Bisa Transfer Saldo',
                  style: GoogleFonts.poppins(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF333333),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Transfer Saldo Tabungan ke sesama pengguna Tabungan Koperasi dengan mudah dan cepat.',
                  style: GoogleFonts.roboto(
                    fontSize: 13,
                    color: const Color(0xFF666666),
                    height: 1.6,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Footer section
  Widget buildFooterSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: const Color(0xFFE8E9EC),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Logo GAS hitam di kiri (positioned center)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Image.asset(
              'assets/logo gas hitam.png',
              height: 100, // Diperbesar dari 80 ke 100
              fit: BoxFit.contain,
              errorBuilder: (c, e, s) =>
                  const SizedBox(height: 100, width: 100),
            ),
          ),
          const SizedBox(width: 24),
          // Quote icon dan text di kanan
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Quote icon besar
                const Icon(
                  Icons.format_quote,
                  size: 32,
                  color: Color(0xFF333333),
                ),
                const SizedBox(height: 8),
                // Quote text dengan Inter Bold
                Text(
                  'Dengan berdirinya Tabungan Koperasi Gusti Artha Sejahtera Semoga dapat memberikan kemudahan dalam mengelola keuangan rekan-rekan semua Gusti Global Group.',
                  style: GoogleFonts.inter(
                    fontSize: 11, // Diperkecil dari 13 ke 11
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF333333),
                    height: 1.7,
                    fontStyle: FontStyle.italic,
                  ),
                ),
                const SizedBox(height: 16),
                // Copyright
                Text(
                  '© PT. Gusti Global Group',
                  style: GoogleFonts.roboto(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: const Color(0xFF999999),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget buildRecentTopupsSection(BuildContext context) {
    if (_recentTopups.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(isDark ? 0.45 : 0.04),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Top-up Terakhir',
            style: GoogleFonts.roboto(
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          ..._recentTopups.map((t) {
            final amount = t['amount'] ?? t['price'] ?? t['nominal'] ?? 0;
            final method = t['method'] ?? t['operator'] ?? '';
            final time = t['created_at'] ?? t['id']?.toString() ?? '';
            String when = time;
            try {
              when = DateFormat(
                'dd MMM yyyy',
              ).format(DateTime.parse(time.toString()));
            } catch (_) {}
            return Padding(
              padding: const EdgeInsets.only(bottom: 8.0),
              child: Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF3EB),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.account_balance_wallet,
                      color: Color(0xFFFF6B2C),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Top-up Rp ${amount.toString()}',
                          style: GoogleFonts.roboto(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '$method • $when',
                          style: GoogleFonts.roboto(
                            fontSize: 12,
                            color:
                                theme.textTheme.bodySmall?.color ?? Colors.grey,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          }).toList(),
        ],
      ),
    );
  }
}

// Widget animasi untuk icon button dengan scale effect
class _AnimatedIconButton extends StatefulWidget {
  final VoidCallback onTap;
  final Widget child;

  const _AnimatedIconButton({required this.onTap, required this.child});

  @override
  State<_AnimatedIconButton> createState() => _AnimatedIconButtonState();
}

class _AnimatedIconButtonState extends State<_AnimatedIconButton> {
  bool _isPressed = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _isPressed = true),
      onTapUp: (_) {
        setState(() => _isPressed = false);
        widget.onTap();
      },
      onTapCancel: () => setState(() => _isPressed = false),
      child: AnimatedScale(
        scale: _isPressed ? 0.85 : 1.0,
        duration: const Duration(milliseconds: 100),
        curve: Curves.easeInOut,
        child: widget.child,
      ),
    );
  }
}

// Widget logo dengan animasi pulse sederhana
class _SimplePulseLogo extends StatefulWidget {
  final Widget child;

  const _SimplePulseLogo({required this.child});

  @override
  State<_SimplePulseLogo> createState() => _SimplePulseLogoState();
}

class _SimplePulseLogoState extends State<_SimplePulseLogo>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(milliseconds: 1500),
      vsync: this,
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: Tween<double>(
        begin: 1.0,
        end: 1.1,
      ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut)),
      child: widget.child,
    );
  }
}
