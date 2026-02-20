import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:get/get.dart';
import 'package:tabungan/services/notification_service.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/page/transfer_gas.dart';
import 'package:tabungan/page/transfer_to_friend.dart';

class TransferPage extends StatefulWidget {
  const TransferPage({super.key});

  @override
  _TransferPageState createState() => _TransferPageState();
}

class _TransferPageState extends State<TransferPage> {
  List<Map<String, dynamic>> _freqRecipients = [];

  @override
  void initState() {
    super.initState();
    _loadFrequentRecipients();
  }

  Future<void> _loadFrequentRecipients() async {
    try {
      final cuser = Get.find<CUser>();
      final id = cuser.user.id;
      if (id == null) return;
      final rec = await EventDB.getFrequentRecipients(id, limit: 6);
      if (rec.isNotEmpty) {
        setState(() {
          _freqRecipients = rec
              .map((r) => {
                    'id': r['id'],
                    'name': r['nama'] ?? r['id'],
                    'phone': r['no_hp'] ?? r['id']?.toString() ?? '',
                  })
              .toList();
        });
      }
    } catch (e) {
      // ignore: avoid_print
      print('loadFrequentRecipients error: $e');
    }
  }

  void _onSelectOption(String key) {
    if (key == 'banks') {
      NotificationHelper.showWarning('Fitur transfer ke bank belum tersedia');
      return;
    }

    if (key == 'ewallet') {
      NotificationHelper.showWarning(
        'Fitur transfer ke E-Wallet belum tersedia',
      );
      return;
    }
  }

  Widget _buildTransferOption(String label, IconData icon) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: BoxDecoration(
        border: Border.all(color: const Color(0xFFE0E0E0)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: const Color(0xFFFF4C00), size: 24),
          const SizedBox(height: 8),
          Text(
            label,
            style: GoogleFonts.roboto(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: const Color(0xFF333333),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: OrangeHeader(title: 'Kirim Uang'),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Section Bank dan E-wallet
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Tambah bank dan e-wallet',
                    style: GoogleFonts.roboto(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: const Color(0xFF333333),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Kamu bisa terima transfer langsung ke',
                    style: GoogleFonts.roboto(
                      fontSize: 14,
                      color: const Color(0xFF666666),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: GestureDetector(
                          onTap: () => _onSelectOption('banks'),
                          child: _buildTransferOption(
                            'Bank',
                            Icons.account_balance,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: GestureDetector(
                          onTap: () => _onSelectOption('ewallet'),
                          child: _buildTransferOption(
                            'E-wallet',
                            Icons.account_balance_wallet,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: GestureDetector(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (context) => const TransferGasPage(),
                              ),
                            );
                          },
                          child: _buildTransferOption('GAS', Icons.swap_horiz),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                ],
              ),
            ),
            const Divider(height: 8, thickness: 8, color: Color(0xFFF5F5F5)),
            // Frequently transferred section
            Container(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Kirim Cepat',
                    style: GoogleFonts.roboto(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: const Color(0xFF333333),
                    ),
                  ),
                  const SizedBox(height: 16),
                  // Frequent recipients: show either placeholder or horizontal list
                  if (_freqRecipients.isEmpty)
                    Container(
                      height: 220,
                      alignment: Alignment.center,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Container(
                            width: 64,
                            height: 64,
                            decoration: const BoxDecoration(
                              color: Color(0xFFFFE4D6),
                              shape: BoxShape.circle,
                            ),
                            child: Center(
                              child: Image.asset(
                                'assets/profile_placeholder.png',
                                width: 40,
                                height: 40,
                                errorBuilder: (context, error, stackTrace) =>
                                    const Icon(
                                      Icons.person,
                                      size: 40,
                                      color: Color(0xFFFF4C00),
                                    ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            'Belum ada kontak disinkronkan',
                            style: GoogleFonts.roboto(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF333333),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Kontak yang sering kamu transfer akan muncul di sini',
                            style: GoogleFonts.roboto(
                              fontSize: 12,
                              color: Color(0xFF666666),
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    )
                  else
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: _freqRecipients.take(5).map((r) {
                          return Padding(
                            padding: const EdgeInsets.only(right: 16),
                            child: GestureDetector(
                              onTap: () {
                                // Directly navigate to TransferToFriend with known info
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => TransferToFriendPage(
                                      userId: Get.find<CUser>().user.id?.toString() ?? '',
                                      phone: r['phone'] ?? '',
                                      recipientName: r['name'],
                                      recipientId: r['id']?.toString(),
                                      isFirstTransfer: false,
                                    ),
                                  ),
                                );
                              },
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Container(
                                    width: 48,
                                    height: 48,
                                    decoration: const BoxDecoration(
                                      color: Color(0xFFFFE4D6),
                                      shape: BoxShape.circle,
                                    ),
                                    child: Center(
                                      child: Text(
                                        r['name'] != null && r['name'].toString().isNotEmpty
                                            ? r['name'][0].toString().toUpperCase()
                                            : '?',
                                        style: GoogleFonts.roboto(
                                          fontSize: 18,
                                          fontWeight: FontWeight.w700,
                                          color: const Color(0xFFFF4C00),
                                        ),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  SizedBox(
                                    width: 72,
                                    child: Text(
                                      r['name'] ?? r['phone'] ?? '',
                                      textAlign: TextAlign.center,
                                      style: GoogleFonts.roboto(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w500,
                                        color: const Color(0xFF333333),
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
