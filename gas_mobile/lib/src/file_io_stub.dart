// Stub for web builds: provide a minimal File class so code that references
// `File.path` compiles. Do NOT implement file IO here — web won't call it.
class File {
  final String path;
  File(this.path);
}
