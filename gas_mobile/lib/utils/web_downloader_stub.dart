import 'dart:typed_data';

// Stub implementation for non-web platforms.
// This will be used when dart:html is not available.

void webDownload(Uint8List bytes, String filename) {
  // noop on non-web; actual saving is handled in mobile/desktop code.
}
