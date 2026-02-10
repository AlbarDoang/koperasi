import 'package:flutter/material.dart';
import 'package:tabungan/utils/custom_toast.dart';

/// Halaman test untuk memverifikasi warna CustomToast
class TestToastPage extends StatelessWidget {
  const TestToastPage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Test Toast Colors'),
        backgroundColor: const Color(0xFFFF4D00),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text(
              'Klik tombol untuk test warna notifikasi:',
              style: TextStyle(fontSize: 16),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 30),

            // Test SUCCESS (HIJAU)
            ElevatedButton(
              onPressed: () {
                CustomToast.success(context, 'Pendaftaran Berhasil');
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF4CAF50),
                padding: const EdgeInsets.symmetric(
                  horizontal: 40,
                  vertical: 15,
                ),
              ),
              child: const Text(
                'TEST SUCCESS (HIJAU)',
                style: TextStyle(color: Colors.white, fontSize: 16),
              ),
            ),

            const SizedBox(height: 20),

            // Test ERROR (MERAH)
            ElevatedButton(
              onPressed: () {
                CustomToast.error(context, 'Pendaftaran gagal');
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.redAccent,
                padding: const EdgeInsets.symmetric(
                  horizontal: 40,
                  vertical: 15,
                ),
              ),
              child: const Text(
                'TEST ERROR (MERAH)',
                style: TextStyle(color: Colors.white, fontSize: 16),
              ),
            ),

            const SizedBox(height: 20),

            // Test WARNING (ORANGE)
            ElevatedButton(
              onPressed: () {
                CustomToast.warning(context, 'Peringatan!');
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.orange,
                padding: const EdgeInsets.symmetric(
                  horizontal: 40,
                  vertical: 15,
                ),
              ),
              child: const Text(
                'TEST WARNING (ORANGE)',
                style: TextStyle(color: Colors.white, fontSize: 16),
              ),
            ),

            const SizedBox(height: 20),

            // Test INFO (BIRU)
            ElevatedButton(
              onPressed: () {
                CustomToast.info(context, 'Informasi');
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.blue,
                padding: const EdgeInsets.symmetric(
                  horizontal: 40,
                  vertical: 15,
                ),
              ),
              child: const Text(
                'TEST INFO (BIRU)',
                style: TextStyle(color: Colors.white, fontSize: 16),
              ),
            ),

            const SizedBox(height: 40),

            const Padding(
              padding: EdgeInsets.all(20.0),
              child: Text(
                '✅ Jika tombol SUCCESS menampilkan notifikasi HIJAU, berarti kode sudah benar!\n\n'
                '❌ Jika masih merah, berarti cache build masih aktif atau API mengembalikan error.',
                style: TextStyle(fontSize: 12, color: Colors.grey),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
