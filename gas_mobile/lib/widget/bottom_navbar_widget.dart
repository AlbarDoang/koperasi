import 'package:flutter/material.dart';
import 'package:curved_navigation_bar/curved_navigation_bar.dart';

class BottomNavBarWidget extends StatelessWidget {
  final int currentIndex;
  final Function(int) onItemTapped;

  const BottomNavBarWidget({
    Key? key,
    required this.currentIndex,
    required this.onItemTapped,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return CurvedNavigationBar(
      color: const Color(0xFFFF4C00),
      buttonBackgroundColor: const Color(0xFFFF4C00),
      backgroundColor: Colors.white,
      height: 55,
      animationCurve: Curves.easeInOut,
      index: currentIndex,
      items: const [
        Icon(Icons.menu_rounded, size: 24, color: Colors.white), // Lainnya
        Icon(Icons.history, size: 24, color: Colors.white), // Riwayat
        Icon(Icons.home, size: 24, color: Colors.white), // Beranda
        Icon(
          Icons.account_balance_wallet,
          size: 24,
          color: Colors.white,
        ), // Tabungan
        Icon(Icons.person_outline, size: 24, color: Colors.white), // Profil
      ],
      onTap: onItemTapped,
    );
  }
}
