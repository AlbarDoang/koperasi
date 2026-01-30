import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class NewsDetailPage extends StatelessWidget {
  final String imagePath;
  final String title;
  final String timeAgo;

  const NewsDetailPage({
    super.key,
    required this.imagePath,
    required this.title,
    required this.timeAgo,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: CustomScrollView(
        slivers: [
          // App bar dengan back button
          SliverAppBar(
            expandedHeight: 0,
            pinned: true,
            backgroundColor: Colors.white,
            elevation: 0,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back, color: Color(0xFF333333)),
              onPressed: () => Navigator.pop(context),
            ),
            title: Text(
              'Pos Security',
              style: GoogleFonts.poppins(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: const Color(0xFF333333),
              ),
            ),
            centerTitle: false,
          ),
          // Content
          SliverToBoxAdapter(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Subtitle
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                  child: Text(
                    'di kantor gusti business distrik',
                    style: GoogleFonts.poppins(
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                      color: const Color(0xFF666666),
                    ),
                  ),
                ),
                // Image dengan border biru
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Container(
                    decoration: BoxDecoration(
                      border: Border.all(
                        color: const Color(0xFF007BFF),
                        width: 3,
                      ),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(5),
                      child: Image.asset(
                        imagePath,
                        width: double.infinity,
                        height: 220,
                        fit: BoxFit.cover,
                        errorBuilder: (c, e, s) => Container(
                          height: 220,
                          color: const Color(0xFFF0F0F0),
                          child: const Icon(
                            Icons.image,
                            size: 80,
                            color: Colors.grey,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                // Content text dengan Lorem Ipsum
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Text(
                    'Pos security gusti business distrik. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
                    style: GoogleFonts.roboto(
                      fontSize: 14,
                      color: const Color(0xFF333333),
                      height: 1.6,
                    ),
                    textAlign: TextAlign.justify,
                  ),
                ),
                const SizedBox(height: 32),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
