import 'dart:typed_data';

import 'web_downloader_stub.dart'
    if (dart.library.html) 'web_downloader_html.dart'
    as _impl;

void webDownload(Uint8List bytes, String filename) =>
    _impl.webDownload(bytes, filename);
