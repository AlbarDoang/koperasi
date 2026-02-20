import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:tabungan/page/transfer_confirm.dart';

class TransferToFriendPage extends StatefulWidget {
  final String userId;
  final String? phone;
  final String? recipientName;
  final String? recipientId;
  final bool isFirstTransfer;
  const TransferToFriendPage({
    Key? key,
    required this.userId,
    this.phone,
    this.recipientName,
    this.recipientId,
    this.isFirstTransfer = false,
  }) : super(key: key);

  @override
  State<TransferToFriendPage> createState() => _TransferToFriendPageState();
}

class _TransferToFriendPageState extends State<TransferToFriendPage> {
  int jumlahNotif = 0;
  late final TextEditingController _amountController;
  late final TextEditingController _noteController;

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController();
    _noteController = TextEditingController();
    _amountController.addListener(() {
      setState(() {});
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  int _amountValue() {
    final cleaned = _amountController.text.replaceAll(RegExp(r'[^0-9]'), '');
    return int.tryParse(cleaned) ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final amountVal = _amountValue();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kirim ke Teman'),
        centerTitle: true,
        elevation: 0,
        backgroundColor: Theme.of(context).appBarTheme.backgroundColor,
        // Tidak ada actions (icon notifikasi dihilangkan)
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Recipient info
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.07),
                      blurRadius: 12,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 22,
                      backgroundColor: const Color(0xFFFFF3E9),
                      child: Icon(Icons.person, color: Color(0xFFFF6A00), size: 28),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.recipientName ?? '-',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF333333),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            widget.phone ?? '',
                            style: TextStyle(
                              fontSize: 13,
                              color: Colors.grey,
                              fontWeight: FontWeight.w400,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFE4D6),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        'BARU',
                        style: TextStyle(
                          fontSize: 11,
                          color: Color(0xFFFF6A00),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'JUMLAH KIRIM',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: Colors.black87,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  hintText: 'Rp 0',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: BorderSide(color: Color(0xFFFF6A00), width: 1.5),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: Color(0xFFFF6A00), width: 1.5),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  suffixIcon: _amountController.text.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear),
                          onPressed: () => _amountController.clear(),
                        )
                      : null,
                ),
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: Colors.black87),
              ),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _quickAmountButton(_amountController, 10000),
                  _quickAmountButton(_amountController, 50000),
                  _quickAmountButton(_amountController, 100000),
                  _quickAmountButton(_amountController, 500000),
                ],
              ),
              const SizedBox(height: 18),
              TextField(
                controller: _noteController,
                decoration: InputDecoration(
                  hintText: 'Tulis catatan "Makasih ya 🙏"',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                  prefixIcon: const Icon(Icons.folder_open),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                ),
                style: TextStyle(fontSize: 15),
              ),
              const SizedBox(height: 18),
              Text(
                'Cek lagi nama penerima dan nominal kirim sudah benar.',
                style: TextStyle(fontSize: 13, color: Colors.black54),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: amountVal > 0
                      ? () {
                          // Navigasi ke halaman konfirmasi
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => TransferConfirmPage(
                                phone: widget.phone ?? '',
                                recipientName: widget.recipientName,
                                amount: amountVal,
                                note: _noteController.text,
                                isFirstTransfer: widget.isFirstTransfer,
                              ),
                            ),
                          );
                        }
                      : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFF6A00),
                    elevation: 2,
                    shadowColor: const Color(0xFFFF6A00).withOpacity(0.2),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: Text(
                    'LANJUT',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 16,
                      letterSpacing: 0.2,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _quickAmountButton(TextEditingController controller, int amount) {
    final formatted = 'Rp ${amount.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]}.')}';
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 2),
        child: OutlinedButton(
          onPressed: () {
            controller.text = formatted;
          },
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: Color(0xFFFF6A00), width: 1.5),
            backgroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          child: Text(
            formatted,
            style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFFFF6A00)),
          ),
        ),
      ),
    );
  }
}