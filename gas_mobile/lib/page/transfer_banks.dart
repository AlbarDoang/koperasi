import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/page/transfer_bank_detail.dart';

class TransferBanksPage extends StatefulWidget {
  const TransferBanksPage({super.key});

  @override
  State<TransferBanksPage> createState() => _TransferBanksPageState();
}

class _TransferBanksPageState extends State<TransferBanksPage> {
  late TextEditingController _searchController;
  final List<String> _allBanks = [
    'BNI',
    'BCA',
    'BRI',
    'CIMB',
    'SMBC',
    'BSI',
    'Permata',
    'Panin',
    'Maybank',
    'Mandiri',
    'Bukopin',
    'Danamon',
  ];
  late List<String> _filteredBanks;

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController();
    _filteredBanks = _allBanks;
    _searchController.addListener(_filterBanks);
  }

  @override
  void dispose() {
    _searchController.removeListener(_filterBanks);
    _searchController.dispose();
    super.dispose();
  }

  void _filterBanks() {
    final query = _searchController.text.toLowerCase();
    setState(() {
      _filteredBanks = _allBanks
          .where((bank) => bank.toLowerCase().contains(query))
          .toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OrangeHeader(title: 'Pilih Bank'),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: TextField(
                controller: _searchController,
                decoration: InputDecoration(
                  hintText: 'Cari bank...',
                  prefixIcon: const Icon(Icons.search),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ),
            Expanded(
              child: _filteredBanks.isEmpty
                  ? Center(
                      child: Text(
                        'Bank tidak ditemukan',
                        style: GoogleFonts.roboto(
                          fontSize: 14,
                          color: Colors.grey,
                        ),
                      ),
                    )
                  : GridView.builder(
                      padding: const EdgeInsets.all(16),
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 4,
                            mainAxisSpacing: 12,
                            crossAxisSpacing: 12,
                            childAspectRatio: 1.1,
                          ),
                      itemCount: _filteredBanks.length,
                      itemBuilder: (context, index) {
                        final bank = _filteredBanks[index];
                        return GestureDetector(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) =>
                                    TransferBankDetailPage(bankName: bank),
                              ),
                            );
                          },
                          child: Container(
                            decoration: BoxDecoration(
                              border: Border.all(
                                color: const Color(0xFFECECEC),
                              ),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFFF3E9),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Center(
                                    child: Text(
                                      bank.length <= 3
                                          ? bank
                                          : bank.substring(0, 3).toUpperCase(),
                                      style: GoogleFonts.roboto(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: const Color(0xFFFF6A00),
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                SizedBox(
                                  width: 62,
                                  child: Text(
                                    bank,
                                    textAlign: TextAlign.center,
                                    style: GoogleFonts.roboto(
                                      fontSize: 11,
                                      color: const Color(0xFF666666),
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
