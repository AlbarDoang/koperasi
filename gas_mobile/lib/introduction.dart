import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'login.dart';
import 'package:shared_preferences/shared_preferences.dart';

class IntroductionPage extends StatefulWidget {
  const IntroductionPage({super.key});

  @override
  State<IntroductionPage> createState() => _IntroductionPageState();
}

class _IntroductionPageState extends State<IntroductionPage> {
  final PageController _controller = PageController();
  int currentPage = 0;

  final List<Map<String, dynamic>> pages = [
    { 
      "title": "Daftar Akun Koperasi GAS",
      "body":
          "Membuat akun Koperasi GAS bisa dengan mendatangi kantor Koperasi GAS atau lewat Aplikasi Koperasi GAS ini.",
      "image": 'assets/slide1.png',
    },
    {
      "title": "Menabung Menjadi Mudah",
      "body":
          "Menabung dengan mendatangi kantor Koperasi GAS untuk bertemu dengan petugas",
      "image": 'assets/slide2.png',
    },
    {
      "title": "Keamanan Tabungan",
      "body":
          "Uang Tabungan anda akan tersimpan aman di Koperasi GAS jadi tidak perlu khawatir",
      "image": 'assets/slide3.png',
    },
    {
      "title": "Bisa Transfer Saldo",
      "body":
          "Transfer Saldo Tabungan ke sesama pengguna Koperasi GAS dengan mudah dan cepat",
      "image": 'assets/slide4.png',
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // SKIP → hanya di halaman 0
            Container(
              alignment: Alignment.centerRight,
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              child: currentPage == 0
                  ? GestureDetector(
                      onTap: () => _controller.jumpToPage(pages.length - 1),
                      child: Text(
                        "Lewati",
                        style: GoogleFonts.poppins(
                          fontSize: 15,
                          fontWeight: FontWeight.w500,
                          color: const Color(0xFFFF4C00),
                        ),
                      ),
                    )
                  : const SizedBox(),
            ),

            // PAGE VIEW
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: pages.length,
                onPageChanged: (index) {
                  setState(() => currentPage = index);
                },
                itemBuilder: (_, index) {
                  return Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Image.asset(pages[index]["image"], height: 260),
                      const SizedBox(height: 25),
                      Text(
                        pages[index]["title"],
                        textAlign: TextAlign.center,
                        style: GoogleFonts.poppins(
                          fontSize: 24,
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFFFF4C00),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 30),
                        child: Text(
                          pages[index]["body"],
                          textAlign: TextAlign.center,
                          style: GoogleFonts.poppins(
                            fontSize: 15,
                            fontWeight: FontWeight.w400,
                            color: Colors.black87,
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),

            const SizedBox(height: 20),

            // NAVIGATION BUTTONS
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 25, vertical: 15),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  // BACK tombol kecuali halaman 0
                  currentPage > 0
                      ? IconButton(
                          icon: const Icon(Icons.arrow_back_ios_new),
                          color: const Color(0xFFFF4C00),
                          onPressed: () {
                            _controller.previousPage(
                              duration: const Duration(milliseconds: 300),
                              curve: Curves.easeInOut,
                            );
                          },
                        )
                      : const SizedBox(width: 40),

                  // DOT INDICATOR
                  Row(
                    children: List.generate(
                      pages.length,
                      (index) => Container(
                        margin: const EdgeInsets.symmetric(horizontal: 4),
                        width: currentPage == index ? 14 : 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: currentPage == index
                              ? const Color(0xFFFF4C00)
                              : Colors.grey.shade400,
                          borderRadius: BorderRadius.circular(20),
                        ),
                      ),
                    ),
                  ),

                  // NEXT / DONE
                  currentPage == pages.length - 1
                      ? GestureDetector(
                          onTap: () => onDone(context),
                          child: Text(
                            "Selesai",
                            style: GoogleFonts.poppins(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: const Color(0xFFFF4C00),
                            ),
                          ),
                        )
                      : IconButton(
                          icon: const Icon(Icons.arrow_forward_ios),
                          color: const Color(0xFFFF4C00),
                          onPressed: () {
                            _controller.nextPage(
                              duration: const Duration(milliseconds: 300),
                              curve: Curves.easeInOut,
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

  // selesai onboarding
  void onDone(context) async {
    final prefs = await SharedPreferences.getInstance();
    // mark onboarding as completed
    await prefs.setBool('ON_BOARDING', true);

    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (context) => const LoginPage()),
    );
  }
}
