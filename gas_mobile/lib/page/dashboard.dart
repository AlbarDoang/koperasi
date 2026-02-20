// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter/services.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/model/user.dart';
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
  final CUser _cUser = Get.find<CUser>();
  bool _isSaldoVisible = false;
  bool _hasUnreadMessages = false;
  bool _bannerShown = false;
  int _currentIndex = 2;

  void getUser() async {
    User? user = await EventPref.getUser();
    if (user != null) {
      _cUser.setUser(user);
      try {
        final fresh = await EventDB.getProfilLengkap(user.id ?? '');
        if (fresh != null) {
          _cUser.setUser(fresh);
          await EventPref.saveUser(fresh);
        }
        else {
          EventDB.refreshSaldoForCurrentUser(idPengguna: user.id);
        }

        try {
          final check = await EventDB.checkPin(id: user.id?.toString());
          if (check != null && check['needs_set_pin'] == true) {
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
  late final VoidCallback _notifListener;

  @override
  void initState() {
    super.initState();
    getUser();
    _loadRecentTopups();
    _updateUnreadNotifications();
    _initializeNotifications();

    // ✅ IMPROVED: Listener yang lebih responsive dengan debug logs
    _notifListener = () {
      if (mounted) {
        debugPrint('[Dashboard] 🔔 _notifListener TRIGGERED - updating badge...');
        _updateUnreadNotifications();
      }
    };
    NotifikasiHelper.onNotificationsChanged.addListener(_notifListener);
    
    // ✅ DEBUG: Print initial state
    if (kDebugMode) {
      debugPrint('[Dashboard] ✅ Listener initialized for badge updates');
    }

    if (widget.bannerMessage != null && widget.bannerMessage!.isNotEmpty) {
      final msg = widget.bannerMessage!;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_bannerShown && mounted) {
          NotificationHelper.showSuccess(msg);
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
    NotifikasiHelper.onNotificationsChanged.removeListener(_notifListener);
    super.dispose();
  }

  Future<void> _initializeNotifications() async {
    await NotifikasiHelper.initializeNotifications();
  }

  /// ✅ IMPROVED: Update unread notifications dengan debug logs
  Future<void> _updateUnreadNotifications() async {
    try {
      final unreadCount = await NotifikasiHelper.getUnreadCount();
      
      debugPrint('[Dashboard] 📊 Unread count: $unreadCount');
      
      if (mounted) {
        setState(() {
          _hasUnreadMessages = unreadCount > 0;
          debugPrint('[Dashboard] ✅ Badge updated - _hasUnreadMessages: $_hasUnreadMessages (count=$unreadCount)');
        });
      }
    } catch (e) {
      debugPrint('[Dashboard] ❌ Error in _updateUnreadNotifications: $e');
    }
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
          Expanded(
            child: RefreshIndicator(
              onRefresh: refreshData,
              child: ListView(
                padding: EdgeInsets.zero,
                physics: const ClampingScrollPhysics(),
                children: <Widget>[
                  buildOrangeHeader(context),
                  buildWhiteMenuSection(context),
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
          setState(() {
            _currentIndex = index;
          });

          switch (index) {
            case 0:
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
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                _SimplePulseLogo(
                  child: Image.asset(
                    'assets/logo gas warna putih.png',
                    height: 64,
                    fit: BoxFit.contain,
                    filterQuality: FilterQuality.high,
                    isAntiAlias: true,
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
                _buildSaldoDisplay(),
                const Spacer(),
                _buildMailIconWithBadge(),
              ],
            ),
            const SizedBox(height: 36),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                buildTopAssetIcon('assets/Donate.png', 'Mulai\nNabung', 0),
                buildTopAssetIcon('assets/Request Money.png', 'Minta', 1),
                buildTopAssetIcon('assets/Exchange.png', 'Kirim', 2),
                buildTopIcon(Icons.qr_code_2, 'Pindai', 3),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget buildWhiteMenuSection(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    return Transform.translate(
      offset: const Offset(0, -24),
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

  Widget _buildWhiteMenuItem({
    required IconData icon,
    required String label,
    required Color color,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: () {
          if (label == 'Kirim') {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (context) => const TransferPage()),
            );
          } else if (label == 'Listrik') {
            NotificationHelper.showInfo('Fitur Isi Listrik belum tersedia');
          } else if (label == 'Isi Pulsa') {
            NotificationHelper.showInfo('Fitur Isi Pulsa belum tersedia');
          } else if (label == 'Isi Kuota') {
            NotificationHelper.showInfo('Fitur Isi Kuota belum tersedia');
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

  Widget _buildWhiteMenuItemWithImage({
    required String imagePath,
    required String label,
    required Color color,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: () {
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
                  Icon(Icons.handshake_outlined, color: color, size: 28),
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

  Widget _buildSaldoDisplay() {
    return Obx(() {
      final saldo = _cUser.user.saldo ?? 0.0;
      final saldoFormatted = CurrencyFormat.toIdr(saldo.toInt());

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

  /// ✅ IMPROVED: Badge notifications icon dengan better logic
  Widget _buildMailIconWithBadge() {
    return GestureDetector(
      onTap: () {
        debugPrint('[Dashboard] 🔔 Notification icon tapped - navigating to /notifikasi');
        Get.toNamed('/notifikasi')?.then((_) {
          debugPrint('[Dashboard] 🔄 Returned from /notifikasi - updating badge immediately');
          // Update badge immediately saat kembali dari notifikasi page
          _updateUnreadNotifications();
        });
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
          // ✅ Badge merah - Visibility based on unread count
          Visibility(
            visible: _hasUnreadMessages,
            child: Positioned(
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
                  Get.toNamed('/mulai_menabung');
                } else if (label == 'Kirim') {
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
                } else if (label == 'Kirim') {
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
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
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
              Image.asset(
                'assets/logo gas warna putih.png',
                height: 100,
                fit: BoxFit.contain,
                errorBuilder: (c, e, s) => const SizedBox(),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
            style: GoogleFonts.roboto(
              fontSize: 13,
              color: Colors.white.withValues(alpha: 0.95),
              height: 1.6,
            ),
          ),
          const SizedBox(height: 28),
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

  Widget buildDaftarAkunSection(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
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

  Widget buildMenabungMudahSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
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

  Widget buildKeamananSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
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

  Widget buildTransferSaldoSection(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
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
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Image.asset(
              'assets/logo gas hitam.png',
              height: 100,
              fit: BoxFit.contain,
              errorBuilder: (c, e, s) =>
                  const SizedBox(height: 100, width: 100),
            ),
          ),
          const SizedBox(width: 24),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.format_quote,
                  size: 32,
                  color: Color(0xFF333333),
                ),
                const SizedBox(height: 8),
                Text(
                  'Dengan berdirinya Tabungan Koperasi Gusti Artha Sejahtera Semoga dapat memberikan kemudahan dalam mengelola keuangan rekan-rekan semua Gusti Global Group.',
                  style: GoogleFonts.inter(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: const Color(0xFF333333),
                    height: 1.7,
                    fontStyle: FontStyle.italic,
                  ),
                ),
                const SizedBox(height: 16),
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