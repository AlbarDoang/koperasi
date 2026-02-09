// ignore_for_file: library_private_types_in_public_api

import 'package:tabungan/src/file_io.dart';
import 'package:flutter/foundation.dart' show kIsWeb, kDebugMode;
import 'package:flutter/material.dart';

import 'package:get/get.dart';
import 'package:tabungan/config/asset.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/login.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/config/api.dart';

class UpdateFoto extends StatefulWidget {
  const UpdateFoto({super.key});

  @override
  _CreateState createState() => _CreateState();
}

class _CreateState extends State<UpdateFoto> {
  final formKey = GlobalKey<FormState>();
  final _controllerFoto = TextEditingController();
  final _controllerIdsiswa = TextEditingController();

  final CUser _cUser = Get.put(CUser());

  @override
  void initState() {
    if (_cUser.user.id != null) {
      _controllerFoto.text = '';
      _controllerIdsiswa.text = '${_cUser.user.id}';
    }
    super.initState();
  }

  // ignore: strict_top_level_inference
  void _onConfirm(context) async {
    var res = await uploadImage(_imageFile!.path, uploadUrl);
    // ignore: avoid_print
    print(res);
    EventPref.clear();
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
    );
    alertberhasil();
  }

  void alertfotokosong() {
    CustomToast.error(context, "Kamu belum memilih foto");
  }

  void alertberhasil() {
    CustomToast.success(
      context,
      "Foto Berhasil diubah. Silahkan Login Kembali",
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OrangeHeader(title: "Update Foto"),
      body: Container(
        width: double.infinity,
        height: double.infinity,
        padding: const EdgeInsets.only(top: 30, left: 20, right: 20),
        child: SingleChildScrollView(
          child: Column(
            children: [
              // const Text('Foto Profil',
              //     textAlign: TextAlign.left,
              //     style: TextStyle(fontSize: 16, fontWeight: FontWeight.w500)),
              const SizedBox(height: 20),
              Center(
                child: FutureBuilder<void>(
                  future: retriveLostData(),
                  builder:
                      (BuildContext context, AsyncSnapshot<void> snapshot) {
                        return _previewImage();
                      },
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                "Tap Foto diatas untuk Memilih Foto",
                style: TextStyle(color: Colors.red, fontSize: 12),
              ),
              const SizedBox(height: 30),
              Container(
                width: 200,
                height: 45,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Asset.colorGreen, Asset.colorBlue],
                    begin: FractionalOffset.topLeft,
                    end: FractionalOffset.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(25),
                ),
                child: TextButton(
                  child: const Padding(
                    padding: EdgeInsets.all(0),
                    child: Text(
                      "UPLOAD FOTO",
                      style: TextStyle(color: Colors.white, fontSize: 17),
                    ),
                  ),
                  onPressed: () {
                    if (_imageFile != null) {
                      _onConfirm(context);
                    } else {
                      alertfotokosong();
                    }
                  },
                ),
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }

  XFile? _imageFile;
  final String uploadUrl = '${Api.baseUrl}/update_foto.php'; // Fixed endpoint path to prevent 404
  final ImagePicker _picker = ImagePicker();

  // ignore: strict_top_level_inference
  Future<String?> uploadImage(filepath, url) async {
    var request = http.MultipartRequest('POST', Uri.parse(url));
    request.files.add(await http.MultipartFile.fromPath('image', filepath));
    // send user id as identifier to the upload API
    request.fields['user_id'] = '${_cUser.user.id}';
    var res = await request.send();

    return res.reasonPhrase;
  }

  Future<void> retriveLostData() async {
    // ignore: deprecated_member_use
    final LostDataResponse response = await _picker.retrieveLostData();
    if (response.isEmpty) {
      return;
    }
    if (response.file != null) {
      setState(() {
        // _imageFile = response.file! as XFile?;
        _imageFile = response.file!;
      });
    } else {
      // ignore: avoid_print
      //print('Retrieve error ' + response.exception!.code);
      if (kDebugMode) {
        print('Retrieve error ${response.exception!.code}');
      }
    }
  }

  Widget _previewImage() {
    if (_imageFile != null) {
      return GestureDetector(
        child: Container(
          decoration: BoxDecoration(
            border: Border.all(color: Colors.blueAccent, width: 2.0),
            borderRadius: BorderRadius.circular(100.0),
          ),
          alignment: Alignment.center,
          width: 200,
          height: 200,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(100.0),
            child: !kIsWeb
                ? Image.file(File(_imageFile!.path), fit: BoxFit.cover)
                : const Center(child: Text('Preview tidak tersedia pada web')),
          ),
        ),
        onTap: () {
          _pickImage();
        },
      );
    } else {
      return GestureDetector(
        child: Container(
          decoration: BoxDecoration(
            color: Colors.blueGrey.withAlpha(20),
            border: Border.all(color: Asset.colorBlue, width: 2.0),
            borderRadius: BorderRadius.circular(100.0),
              image: DecorationImage(
              fit: BoxFit.cover,
              image: NetworkImage(
                "${Api.baseUrl}/assets/images/user.png",
              ),
            ),
          ),
          height: 150,
          width: 150,
        ),
        onTap: () {
          _pickImage();
        },
      );
    }
  }

  void _pickImage() async {
    try {
      final XFile? pickedFile = await _picker.pickImage(
        source: ImageSource.gallery,
      );
      setState(() {
        _imageFile = pickedFile as XFile;
      });
    } catch (e) {
      // ignore: avoid_print
      print("Image picker error $e");
    }
  }
}
