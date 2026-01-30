// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';

// Import halaman
import 'package:tabungan/page/lainnya.dart';
import 'package:tabungan/page/riwayat.dart';
import 'package:tabungan/page/dashboard.dart';
import 'package:tabungan/page/tabungan.dart';
import 'package:tabungan/page/profil.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  _HomePageState createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int _selectedIndex = 2; // Default ke Dashboard

  // Daftar halaman
  late final List<Widget> _pages;

  @override
  void initState() {
    super.initState();
    _pages = [
      LainnyaPage(),
      RiwayatTransaksiPage(),
      Dashboard(),
      TabunganPage(),
      Profil(),
    ];
  }

  void _onItemTapped(int index) {
    if (_selectedIndex == index)
      return; // biar gak rebuild ulang kalau tab sama
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _selectedIndex, children: _pages),
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,
        currentIndex: _selectedIndex,
        onTap: _onItemTapped,
        selectedItemColor: Colors.orange,
        unselectedItemColor: Colors.grey,
        showUnselectedLabels: true,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.menu_rounded),
            label: 'Lainnya',
          ),
          BottomNavigationBarItem(icon: Icon(Icons.history), label: 'Riwayat'),
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Beranda'),
          BottomNavigationBarItem(
            icon: Icon(Icons.account_balance_wallet),
            label: 'Tabungan',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            label: 'Profil',
          ),
        ],
      ),
    );
  }
}
