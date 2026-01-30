import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/page/transfer_to_friend.dart';
import 'package:tabungan/event/event_db.dart';

class TransferGasPage extends StatefulWidget {
  const TransferGasPage({super.key});

  @override
  _TransferGasPageState createState() => _TransferGasPageState();
}

class _TransferGasPageState extends State<TransferGasPage> {
  final TextEditingController _searchController = TextEditingController();
  List<Map<String, dynamic>> _contacts = [];

  List<Map<String, dynamic>> _filteredContacts = [];
  String? _newPhone;
  bool _showNewResult = false;

  @override
  void initState() {
    super.initState();
    _filteredContacts = _contacts;
    _searchController.addListener(_filterContacts);
    // Load frequent recipients for this user
    _loadFrequentRecipients();
  }

  Future<void> _loadFrequentRecipients() async {
    try {
      final cuser = Get.find<CUser>();
      final id = cuser.user.id;
      if (id == null) return;
      final rec = await EventDB.getFrequentRecipients(id, limit: 10);
      if (rec.isNotEmpty) {
        setState(() {
          _contacts = rec
              .map(
                (r) => {
                  'name': r['nama'] ?? r['id'] ?? 'Penerima',
                  'phone': r['no_hp'] ?? r['id']?.toString() ?? '',
                  'avatar': null,
                  'id': r['id'],
                },
              )
              .toList();
          _filteredContacts = _contacts;
        });
      }
    } catch (e) {
      if (kDebugMode) print('loadFrequentRecipients error: $e');
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _filterContacts() {
    final query = _searchController.text.toLowerCase();
    final cleanQuery = query.replaceAll(RegExp(r'[^0-9]'), '');

    final isNewPhone =
        _isValidPhone(query) &&
        !_isPhoneInContacts(query) &&
        cleanQuery.isNotEmpty;

    setState(() {
      if (isNewPhone) {
        // When it's a new valid phone, show the two result cards inline
        _filteredContacts = [];
        _newPhone = query;
        _showNewResult = true;
      } else {
        _newPhone = null;
        _showNewResult = false;
        _filteredContacts = _contacts
            .where(
              (contact) =>
                  contact['name'].toLowerCase().contains(query) ||
                  contact['phone'].contains(query),
            )
            .toList();
      }
    });
  }

  bool _isValidPhone(String phone) {
    // Validasi format nomor telepon Indonesia
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
    return cleanPhone.length >= 10 && cleanPhone.length <= 13;
  }

  bool _isPhoneInContacts(String phone) {
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
    return _contacts.any(
      (c) => c['phone'].replaceAll(RegExp(r'[^0-9]'), '') == cleanPhone,
    );
  }

  void _showVerificationDialog(String phone) async {
    // Try to lookup user by phone to show friendly name before confirming
    final user = await EventDB.inspectUser(phone);
    // Show confirmation bottom sheet (reuse for both contact & bank flows)
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return DraggableScrollableSheet(
          expand: false,
          initialChildSize: 0.4,
          minChildSize: 0.25,
          maxChildSize: 0.9,
          builder: (_, controller) {
            return Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Theme.of(context).scaffoldBackgroundColor,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(20),
                ),
              ),
              child: ListView(
                controller: controller,
                children: [
                  const SizedBox(height: 8),
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.grey[300],
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Center(
                    child: Container(
                      width: 88,
                      height: 88,
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF3E9),
                        shape: BoxShape.circle,
                      ),
                      child: const Center(
                        child: Icon(
                          Icons.person_outline,
                          size: 48,
                          color: Color(0xFFFF6A00),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Center(
                    child: Text(
                      user != null
                          ? 'Kirim ke ${user['nama']}'
                          : 'Tetap waspada saat kirim uang',
                      style: GoogleFonts.roboto(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFF333333),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Center(
                    child: Text(
                      'Uang tidak bisa kembali kalau salah transfer. Pastikan kamu kenal sama penerimanya ya.',
                      style: GoogleFonts.roboto(
                        fontSize: 13,
                        color: Theme.of(context).textTheme.bodySmall?.color,
                        height: 1.4,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(context).pop(),
                          style: OutlinedButton.styleFrom(
                            side: BorderSide(color: Colors.grey.shade300),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          child: Text(
                            'CEK LAGI',
                            style: GoogleFonts.roboto(
                              color: Colors.black87,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () {
                            Navigator.of(context).pop();
                            // Navigate to transfer page, pass name if available
                            Navigator.of(this.context).push(
                              MaterialPageRoute(
                                builder: (_) => TransferToFriendPage(
                                  phone: phone,
                                  recipientName: user != null
                                      ? user['nama']
                                      : null,
                                  recipientId: user != null
                                      ? user['id']?.toString()
                                      : null,
                                ),
                              ),
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFFF4C00),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          child: Text(
                            'YA, KENAL',
                            style: GoogleFonts.roboto(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildContactAvatar(String? avatarPath, String name) {
    if (avatarPath != null) {
      return CircleAvatar(
        radius: 32,
        backgroundImage: AssetImage(avatarPath),
        onBackgroundImageError: (exception, stackTrace) {
          // Fallback jika gambar tidak ada
        },
      );
    }

    return CircleAvatar(
      radius: 32,
      backgroundColor: const Color(0xFFFFE4D6),
      child: Icon(Icons.person, color: const Color(0xFFFF4C00), size: 32),
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
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Search Box Section
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
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF5F5F5),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: const Color(0xFFE0E0E0)),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.search,
                          color: Color(0xFF999999),
                          size: 20,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextField(
                            controller: _searchController,
                            keyboardType: TextInputType.phone,
                            decoration: InputDecoration(
                              border: InputBorder.none,
                              hintText: 'Masukkan nomor telepon',
                              hintStyle: GoogleFonts.roboto(
                                fontSize: 14,
                                color: const Color(0xFF999999),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const Divider(height: 1, color: Color(0xFFEEEEEE), thickness: 1),

            // Kirim Cepat (Recent contacts)
            if (_filteredContacts.isNotEmpty && _searchController.text.isEmpty)
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
                    const SizedBox(height: 12),
                    if (_contacts.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Text(
                          'Belum ada riwayat transfer',
                          style: GoogleFonts.roboto(
                            fontSize: 13,
                            color: Theme.of(context).textTheme.bodySmall?.color,
                          ),
                        ),
                      )
                    else
                      SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: _contacts.take(5).map((contact) {
                            return Padding(
                              padding: const EdgeInsets.only(right: 16),
                              child: GestureDetector(
                                onTap: () =>
                                    _showVerificationDialog(contact['phone']),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    _buildContactAvatar(
                                      contact['avatar'],
                                      contact['name'],
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      contact['name'],
                                      textAlign: TextAlign.center,
                                      style: GoogleFonts.roboto(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w500,
                                        color: const Color(0xFF333333),
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

            const Divider(height: 1, color: Color(0xFFEEEEEE), thickness: 1),

            // Semua Kontak Section
            Container(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Semua Kontak',
                        style: GoogleFonts.roboto(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFF333333),
                        ),
                      ),
                      TextButton.icon(
                        onPressed: () async {
                          // Placeholder: contact sync not yet implemented
                          Get.snackbar(
                            'Info',
                            'Sinkronisasi kontak akan datang di pembaruan berikutnya.',
                            backgroundColor: Colors.orange.shade700,
                            colorText: Colors.white,
                            snackPosition: SnackPosition.TOP,
                            margin: const EdgeInsets.all(16),
                          );
                        },
                        icon: const Icon(Icons.sync, size: 18),
                        label: const Text('Sinkronkan'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  if (_showNewResult && _newPhone != null)
                    Column(
                      children: [
                        // KONTAK result card (inline)
                        GestureDetector(
                          onTap: () => _showVerificationDialog(_newPhone!),
                          child: Container(
                            width: double.infinity,
                            margin: const EdgeInsets.symmetric(vertical: 8),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Theme.of(context).cardColor,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color: const Color(0xFFECECEC),
                              ),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFFF3E9),
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: const Center(
                                    child: Icon(
                                      Icons.person,
                                      color: Color(0xFFFF6A00),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _newPhone!,
                                        style: GoogleFonts.roboto(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w600,
                                          color: const Color(0xFF333333),
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        'Kamu baru pertama kali kirim ke nomor ini. Pastikan tujuannya sudah benar dan tepercaya.',
                                        style: GoogleFonts.roboto(
                                          fontSize: 11,
                                          color: Theme.of(
                                            context,
                                          ).textTheme.bodySmall?.color,
                                        ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ],
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 6,
                                    vertical: 2,
                                  ),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFFF3E9),
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: Text(
                                    'BARU',
                                    style: GoogleFonts.roboto(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w700,
                                      color: const Color(0xFFFF6A00),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),

                        // REKENING BANK result card (inline)
                        GestureDetector(
                          onTap: () => _showVerificationDialog(_newPhone!),
                          child: Container(
                            width: double.infinity,
                            margin: const EdgeInsets.symmetric(vertical: 8),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Theme.of(context).cardColor,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color: const Color(0xFFECECEC),
                              ),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFFF3E9),
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: const Center(
                                    child: Icon(
                                      Icons.account_balance,
                                      color: Color(0xFFFF6A00),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _newPhone!,
                                        style: GoogleFonts.roboto(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w600,
                                          color: const Color(0xFF333333),
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        'Kamu baru pertama kali kirim ke akun bank ini. Pastikan nomornya sudah sesuai ya.',
                                        style: GoogleFonts.roboto(
                                          fontSize: 11,
                                          color: Theme.of(
                                            context,
                                          ).textTheme.bodySmall?.color,
                                        ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ],
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 6,
                                    vertical: 2,
                                  ),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFFF3E9),
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: Text(
                                    'BARU',
                                    style: GoogleFonts.roboto(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w700,
                                      color: const Color(0xFFFF6A00),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    )
                  else
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 4,
                            mainAxisSpacing: 16,
                            crossAxisSpacing: 12,
                            childAspectRatio: 0.85,
                          ),
                      itemCount: _filteredContacts.length,
                      itemBuilder: (context, index) {
                        final contact = _filteredContacts[index];
                        return GestureDetector(
                          onTap: () {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  'Memilih: ${contact['name']} - ${contact['phone']}',
                                ),
                                duration: const Duration(seconds: 1),
                              ),
                            );
                          },
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              _buildContactAvatar(
                                contact['avatar'],
                                contact['name'],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                contact['name'],
                                textAlign: TextAlign.center,
                                style: GoogleFonts.roboto(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: const Color(0xFF333333),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        );
                      },
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
