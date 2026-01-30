import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class OrangeHeader extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final VoidCallback? onBackPressed;
  final List<Widget>? actions; // <-- Tambahan

  const OrangeHeader({
    super.key,
    required this.title,
    this.onBackPressed,
    this.actions, // <-- Tambahan
  });

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: const Color(0xFFFF5F0A),
      elevation: 0,
      centerTitle: true,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back, color: Colors.white),
        onPressed: onBackPressed ?? () => Navigator.pop(context),
      ),
      title: Text(
        title,
        style: GoogleFonts.poppins(
          color: Colors.white,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
      iconTheme: const IconThemeData(color: Colors.white),
      actions: actions, // <-- Tambahan
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}
