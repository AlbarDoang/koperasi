import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

void showCustomBanner(
  BuildContext context,
  String message, {
  Color color = Colors.orange,
}) async {
  ScaffoldMessenger.of(context).hideCurrentMaterialBanner();
  await Future.delayed(const Duration(milliseconds: 100));

  ScaffoldMessenger.of(context).showMaterialBanner(
    MaterialBanner(
      backgroundColor: Colors.transparent,
      elevation: 0,
      dividerColor: Colors.transparent,
      content: Container(
        margin: const EdgeInsets.symmetric(horizontal: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: Colors.black26.withOpacity(0.1),
              blurRadius: 6,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Row(
          children: [
            Icon(
              color == Colors.redAccent
                  ? Icons.error_outline_rounded
                  : Icons.info_outline_rounded,
              color: color,
              size: 28,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                style: GoogleFonts.kanit(
                  color: Colors.black87,
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
            ),
          ],
        ),
      ),
      actions: const [SizedBox.shrink()],
    ),
  );

  Future.delayed(const Duration(seconds: 3), () {
    if (ScaffoldMessenger.of(context).mounted) {
      ScaffoldMessenger.of(context).hideCurrentMaterialBanner();
    }
  });
}
