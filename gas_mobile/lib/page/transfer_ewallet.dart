import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/page/transfer_ewallet_detail.dart';

class TransferEwalletPage extends StatefulWidget {
  const TransferEwalletPage({super.key});

  @override
  State<TransferEwalletPage> createState() => _TransferEwalletPageState();
}

class _TransferEwalletPageState extends State<TransferEwalletPage> {
  late TextEditingController _searchController;
  final List<String> _allEwallets = [
    'Alfamart',
    'Alfamidi',
    'GoPay',
    'OVO',
    'DANA',
    'Grab',
    'LinkAja',
    'Mandiri E-Money',
  ];
  late List<String> _filteredEwallets;

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController();
    _filteredEwallets = _allEwallets;
    _searchController.addListener(_filterEwallets);
  }

  @override
  void dispose() {
    _searchController.removeListener(_filterEwallets);
    _searchController.dispose();
    super.dispose();
  }

  void _filterEwallets() {
    final query = _searchController.text.toLowerCase();
    setState(() {
      _filteredEwallets = _allEwallets
          .where((ewallet) => ewallet.toLowerCase().contains(query))
          .toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OrangeHeader(title: 'Pilih E-wallet'),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: TextField(
                controller: _searchController,
                decoration: InputDecoration(
                  hintText: 'Cari e-wallet...',
                  prefixIcon: const Icon(Icons.search),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ),
            Expanded(
              child: _filteredEwallets.isEmpty
                  ? Center(
                      child: Text(
                        'E-wallet tidak ditemukan',
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
                      itemCount: _filteredEwallets.length,
                      itemBuilder: (context, index) {
                        final ewallet = _filteredEwallets[index];
                        return GestureDetector(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => TransferEwalletDetailPage(
                                  ewalletName: ewallet,
                                ),
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
                                      ewallet.length <= 3
                                          ? ewallet
                                          : ewallet
                                                .substring(0, 3)
                                                .toUpperCase(),
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
                                    ewallet,
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
