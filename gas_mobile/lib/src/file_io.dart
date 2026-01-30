// Conditional export: use real dart:io on native, stub on web.
export 'file_io_real.dart' if (dart.library.html) 'file_io_stub.dart';
