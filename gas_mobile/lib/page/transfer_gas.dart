import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:get/get.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:permission_handler/permission_handler.dart';
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
  List<Contact> _syncedContacts = [];
  List<Map<String, dynamic>> _filteredContacts = [];
  List<Contact> _filteredSyncedContacts = [];
  String? _newPhone;
  bool _showNewResult = false;
  bool _isLoading = false;
  String? _newRecipientName;
  String? _newRecipientId;
  bool _isFirstTransfer = true;
  bool _isLookingUp = false;
  Set<String> _allPastRecipientIds = {};
  Set<String> _allPastRecipientPhones = {};

  @override
  void initState() {
    super.initState();
    _filteredContacts = _contacts;
    _filteredSyncedContacts = _syncedContacts;
    _searchController.addListener(_filterContacts);
    _loadFrequentRecipients();
  }

  Future<void> _loadFrequentRecipients() async {
    try {
      final cuser = Get.find<CUser>();
      final id = cuser.user.id;
      if (id == null) return;
      final rec = await EventDB.getFrequentRecipients(id, limit: 1000);
      if (rec.isNotEmpty) {
        setState(() {
          _contacts = rec
              .take(10)
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
          _allPastRecipientIds = rec
              .map((r) => r['id']?.toString() ?? '')
              .where((s) => s.isNotEmpty)
              .toSet();
          _allPastRecipientPhones = rec
              .map(
                (r) => (r['no_hp'] ?? '').toString().replaceAll(
                  RegExp(r'[^0-9]'),
                  '',
                ),
              )
              .where((s) => s.isNotEmpty)
              .toSet();
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
        !_isPhoneInSyncedContacts(query) &&
        cleanQuery.isNotEmpty;
    setState(() {
      if (isNewPhone) {
        _filteredContacts = [];
        _filteredSyncedContacts = [];
        _newPhone = query;
        _showNewResult = true;
      } else {
        _newPhone = null;
        _showNewResult = false;
        _newRecipientName = null;
        _newRecipientId = null;
        _isFirstTransfer = true;
        _filteredContacts = _contacts
            .where(
              (contact) =>
                  contact['name'].toLowerCase().contains(query) ||
                  contact['phone'].contains(query),
            )
            .toList();
        _filteredSyncedContacts = _filterSyncedContacts(query);
      }
    });
    if (isNewPhone) {
      _lookupRecipient(query);
    }
  }

  Future<void> _lookupRecipient(String phone) async {
    setState(() {
      _isLookingUp = true;
    });
    try {
      final user = await EventDB.inspectUser(phone);
      if (_newPhone != phone) return;
      if (user != null) {
        final recipientId = user['id']?.toString() ?? '';
        final recipientName = user['nama']?.toString();
        final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
        bool hasTransferredBefore = false;
        if (recipientId.isNotEmpty &&
            _allPastRecipientIds.contains(recipientId)) {
          hasTransferredBefore = true;
        }
        if (cleanPhone.isNotEmpty &&
            _allPastRecipientPhones.contains(cleanPhone)) {
          hasTransferredBefore = true;
        }
        setState(() {
          _newRecipientName = recipientName;
          _newRecipientId = recipientId;
          _isFirstTransfer = !hasTransferredBefore;
          _isLookingUp = false;
        });
      } else {
        setState(() {
          _newRecipientName = null;
          _newRecipientId = null;
          _isFirstTransfer = true;
          _isLookingUp = false;
        });
      }
    } catch (e) {
      if (kDebugMode) print('lookupRecipient error: $e');
      setState(() {
        _isLookingUp = false;
      });
    }
  }

  bool _isValidPhone(String phone) {
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
    return cleanPhone.length >= 10 && cleanPhone.length <= 13;
  }

  bool _isPhoneInContacts(String phone) {
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
    return _contacts.any(
      (c) => c['phone'].replaceAll(RegExp(r'[^0-9]'), '') == cleanPhone,
    );
  }

  bool _isPhoneInSyncedContacts(String phone) {
    final cleanPhone = _normalizePhone(phone);
    return _syncedContacts.any((contact) {
      final phones = contact.phones;
      if (phones == null || phones.isEmpty) return false;
      return phones.any(
        (p) => _normalizePhone(p.number) == cleanPhone,
      );
    });
  }

  String _normalizePhone(String phone) {
    final cleaned = phone.replaceAll(RegExp(r'[^0-9+]'), '');
    if (cleaned.isEmpty) return '';
    if (cleaned.startsWith('+')) {
      return cleaned;
    }
    if (cleaned.startsWith('0')) {
      return '+62${cleaned.substring(1)}';
    }
    return '+$cleaned';
  }

  List<Contact> _filterSyncedContacts(String query) {
    if (query.isEmpty) return List<Contact>.from(_syncedContacts);
    final lower = query.toLowerCase();
    final cleanQuery = _normalizePhone(query);
    return _syncedContacts.where((contact) {
      final name = (contact.displayName ?? '').toLowerCase();
      final phones = contact.phones ?? [];
      final phoneMatch = phones.any((p) {
        final value = p.number;
        return value.contains(query) || _normalizePhone(value).contains(cleanQuery);
      });
      return name.contains(lower) || phoneMatch;
    }).toList();
  }

  Future<void> _handleSyncContacts() async {
    final allow = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: Text(
            'Konfirmasi Akses Kontak',
            style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
          ),
          content: Text(
            'Aplikasi ingin mengakses kontak Anda untuk memudahkan transfer uang',
            style: GoogleFonts.roboto(fontSize: 13),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Batal'),
            ),
            TextButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Izinkan'),
            ),
          ],
        );
      },
    );
    if (allow == true) {
      await _syncContacts();
    }
  }

  Future<bool> _syncContacts() async {
    setState(() {
      _isLoading = true;
    });
    try {
      var status = await Permission.contacts.status;
      if (!status.isGranted) {
        status = await Permission.contacts.request();
      }
      if (status.isPermanentlyDenied) {
        await openAppSettings();
        return false;
      }
      if (!status.isGranted) {
        if (!mounted) return false;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Izin akses kontak ditolak')),
        );
        return false;
      }
      final contacts = await FlutterContacts.getContacts(
        withProperties: true,
        withPhoto: false,
        withThumbnail: false,
      );
      if (!mounted) return false;
      setState(() {
        _syncedContacts = contacts.toList();
        _filteredSyncedContacts = _filterSyncedContacts(
          _searchController.text,
        );
      });
      return true;
    } catch (e) {
      if (kDebugMode) print('syncContacts error: $e');
      return false;
    } finally {
      if (!mounted) return false;
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _goToTransfer({
    required String phone,
    String? recipientName,
    String? recipientId,
    bool isFirstTransfer = true,
  }) {
    final cuser = Get.find<CUser>();
    final userId = cuser.user.id?.toString() ?? '';
    if (isFirstTransfer) {
      _showVerificationDialog(phone, isFirstTransfer: isFirstTransfer);
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TransferToFriendPage(
          userId: userId,
          phone: phone,
          recipientName: recipientName,
          recipientId: recipientId,
          isFirstTransfer: isFirstTransfer,
        ),
      ),
    );
  }

  void _showVerificationDialog(String phone, {bool isFirstTransfer = true}) async {
    final user = await EventDB.inspectUser(phone);
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
                            final cuser = Get.find<CUser>();
                            final userId = cuser.user.id?.toString() ?? '';
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => TransferToFriendPage(
                                  userId: userId,
                                  phone: phone,
                                  recipientName: user != null
                                      ? user['nama']
                                      : null,
                                  recipientId: user != null
                                      ? user['id']?.toString()
                                      : null,
                                  isFirstTransfer: isFirstTransfer,
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
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Text(
              'Kirim Cepat',
              style: GoogleFonts.roboto(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: const Color(0xFF333333),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFF5F5F5),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFE0E0E0)),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.search,
                    color: Color(0xFF999999),
                    size: 20,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: TextField(
                      controller: _searchController,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(
                        border: InputBorder.none,
                        hintText: 'Masukkan nomor telepon',
                        hintStyle: GoogleFonts.roboto(
                          fontSize: 14,
                          color: Color(0xFF999999),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Semua Kontak',
                  style: GoogleFonts.roboto(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF333333),
                  ),
                ),
                TextButton.icon(
                  onPressed: _isLoading ? null : _handleSyncContacts,
                  icon: Icon(Icons.sync, size: 18),
                  label: Text('Sinkronkan'),
                ),
              ],
            ),
          ),
          Expanded(
            child: Builder(
              builder: (context) {
                if (_showNewResult && _newPhone != null) {
                  return Column(
                    children: [
                      GestureDetector(
                        onTap: () => _goToTransfer(
                          phone: _newPhone!,
                          recipientName: _newRecipientName,
                          recipientId: _newRecipientId,
                          isFirstTransfer: _isFirstTransfer,
                        ),
                        child: Container(
                          width: double.infinity,
                          margin: EdgeInsets.symmetric(vertical: 8),
                          padding: EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: Color(0xFFECECEC),
                            ),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 40,
                                height: 40,
                                decoration: BoxDecoration(
                                  color: Color(0xFFFFF3E9),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Center(
                                  child: Icon(
                                    Icons.person,
                                    color: Color(0xFFFF6A00),
                                  ),
                                ),
                              ),
                              SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _isLookingUp
                                          ? _newPhone!
                                          : (_newRecipientName != null && _newRecipientName!.isNotEmpty)
                                              ? '$_newRecipientName ($_newPhone)'
                                              : _newPhone!,
                                      style: GoogleFonts.roboto(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                        color: Color(0xFF333333),
                                      ),
                                    ),
                                    if (_newRecipientName != null && _newRecipientName!.isNotEmpty && _isFirstTransfer)
                                      SizedBox(height: 4),
                                    if (_newRecipientName != null && _newRecipientName!.isNotEmpty && _isFirstTransfer)
                                      Text(
                                        'Kamu baru pertama kali kirim ke nomor ini. Pastikan tujuannya sudah benar dan tepercaya.',
                                        style: GoogleFonts.roboto(
                                          fontSize: 11,
                                          color: Theme.of(context).textTheme.bodySmall?.color,
                                        ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                  ],
                                ),
                              ),
                              if (_isFirstTransfer)
                                Container(
                                  padding: EdgeInsets.symmetric(
                                    horizontal: 6,
                                    vertical: 2,
                                  ),
                                  decoration: BoxDecoration(
                                    color: Color(0xFFFFF3E9),
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: Text(
                                    'BARU',
                                    style: GoogleFonts.roboto(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFFFF6A00),
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  );
                }
                if (_isLoading) {
                  return Center(
                    child: Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: CircularProgressIndicator(),
                    ),
                  );
                }
                if (_filteredSyncedContacts.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.contact_page,
                          size: 80,
                          color: Colors.grey,
                        ),
                        SizedBox(height: 12),
                        Text(
                          'Belum ada kontak disinkronkan',
                          style: GoogleFonts.roboto(
                            fontSize: 13,
                            color: Colors.grey,
                          ),
                        ),
                      ],
                    ),
                  );
                }
                // Default: tampilkan daftar kontak
                return ListView.separated(
                  itemCount: _filteredSyncedContacts.length,
                  separatorBuilder: (_, __) => Divider(height: 1),
                  itemBuilder: (context, index) {
                    final contact = _filteredSyncedContacts[index];
                    final name = contact.displayName ?? 'Kontak';
                    final rawPhone = (contact.phones != null && contact.phones!.isNotEmpty)
                        ? contact.phones!.first.number
                        : '';
                    final firstPhone = rawPhone.trim().isEmpty ? '-' : rawPhone;
                    final canTransfer = firstPhone != '-';
                    return ListTile(
                      contentPadding: EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 2,
                      ),
                      leading: CircleAvatar(
                        backgroundColor: Color(0xFFFFE4D6),
                        child: Icon(
                          Icons.person,
                          color: Color(0xFFFF6A00),
                        ),
                      ),
                      title: Text(
                        name,
                        style: GoogleFonts.roboto(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF333333),
                        ),
                      ),
                      subtitle: Text(
                        firstPhone,
                        style: GoogleFonts.roboto(
                          fontSize: 12,
                          color: Theme.of(context).textTheme.bodySmall?.color,
                        ),
                      ),
                      onTap: canTransfer
                          ? () => _goToTransfer(
                                phone: firstPhone,
                                recipientName: name,
                                isFirstTransfer: false,
                              )
                          : null,
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
