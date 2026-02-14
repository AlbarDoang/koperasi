import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:tabungan/utils/currency_format.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/event/event_pref.dart';
import 'dart:async';

class TabunganPage extends StatefulWidget {
  final VoidCallback? onBackToDashboard;

  const TabunganPage({Key? key, this.onBackToDashboard}) : super(key: key);

  @override
  State<TabunganPage> createState() => _TabunganPageState();
}

class _TabunganPageState extends State<TabunganPage>
    with WidgetsBindingObserver {
  final GlobalKey _jenisKey = GlobalKey();
  final GlobalKey _periodeKey = GlobalKey();

  Timer? _pollTimer;
  int _pollAttempts = 0;

  void _startShortPoll() {
    if (_pollTimer != null) return; // already polling
    _pollAttempts = 0;
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (t) async {
      _pollAttempts++;
      if (_pollAttempts > 6) {
        _pollTimer?.cancel();
        _pollTimer = null;
        return;
      }
      await _loadServerData();
    });
  }

  void _stopPoll() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  // Will be populated from server (jenis_list). Keep a small local fallback.
  // Each entry will be a Map with { 'id': int?, 'nama': String }
  // jenis list will be populated from server response; keep empty until fetched
  List<Map<String, dynamic>> _jenisList = [];

  final List<String> _periodeOptions = [
    'Hari Sebelumnya',
    '7 Hari Terakhir',
    '30 Hari Terakhir',
    'Tahun Lalu',
  ];

  int _totalTabungan = 0; // use server total when available
  int _saldoUtama = 0; // authoritative saldo from get_saldo_tabungan.php

  String _selectedJenis = 'Tabungan Reguler';
  String _selectedPeriode = '30 Hari Terakhir';
  // State fetched from server
  Map<String, List<Map<String, dynamic>>> _historyByJenis = {};
  Map<String, int> _totalsByJenis = {};
  bool _loading = true;
  String _userName = '-';
  String _userRole = '';

  // Keep selected id of jenis tabungan to ensure we send integer id
  int? selectedIdJenisTabungan;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _fetchSaldoUtama();
    _loadServerData();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stopPoll();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // refresh when app comes back to foreground (e.g., after admin approval)
      _fetchSaldoUtama();
      _loadServerData();
    }
  }

  Future<void> _loadServerData() async {
    setState(() => _loading = true);
    try {
      final user = await EventPref.getUser();
      // Refresh authoritative saldo immediately so UI shows latest value
      try {
        await EventDB.refreshSaldoForCurrentUser();
      } catch (e) {
        if (kDebugMode) print('refreshSaldo on load error: $e');
      }
      // Also fetch saldo utama from saldo-tabungan API
      try {
        await _fetchSaldoUtama();
      } catch (e) {
        if (kDebugMode) print('fetchSaldoUtama on load error: $e');
      }

      // Prefer internal id or phone (User model doesn't store id_tabungan)
      final idTab = user?.id ?? user?.no_hp ?? '';
      if (idTab.isNotEmpty) {
        if (user != null) {
          _userName = user.nama ?? user.nama_lengkap ?? _userName;
          _userRole = user.status_akun ?? '';
        }

        // Helper: normalize jenis names for comparison (strip "Tabungan")
        String _normJenis(String s) {
          return s.toString().toLowerCase().replaceAll(RegExp(r"\btabungan\b", caseSensitive: false), '').trim();
        }

        // Fetch totals per jenis from server (authoritative); ensure ALL static jenis are shown
        final rinc = await EventDB.getRincianTabungan(idTab);
        print('DEBUG getRincianTabungan raw: $rinc');
        // Build jenis list and totals from server 'rinc' response
        final Map<String, int> totals = {};
        final List<Map<String, dynamic>> jenisFromServer = [];

        // Fetch master jenis list as fallback for resolving missing ids
        final masterJenis = await EventDB.getJenisMaster();
        if (kDebugMode) print('DEBUG master jenis count: ${masterJenis.length}');

        for (var r in rinc) {
          final jenisName = (r['jenis'] ?? '').toString();
          int? idVal = r['id'] != null ? int.tryParse(r['id'].toString()) : null;
          final total = int.tryParse((r['total'] ?? 0).toString()) ?? 0;

          // If server didn't provide id, try to find it in master jenis by name
          if (idVal == null && jenisName.isNotEmpty && masterJenis.isNotEmpty) {
            // case-insensitive match against masterJenis 'nama'
            final foundMaster = masterJenis.firstWhere(
              (m) => (m['nama'] ?? '').toString().toLowerCase() == jenisName.toLowerCase(),
              orElse: () => {},
            );
            if (foundMaster != null && (foundMaster is Map) && foundMaster.containsKey('id')) {
              idVal = foundMaster['id'];
              if (kDebugMode) print('DEBUG resolved id for $jenisName -> $idVal from master jenis');
            }
          }

          if (jenisName.isNotEmpty) {
            totals[jenisName] = total;
            // Ensure each item contains the required keys for UI and Cairkan flow
            jenisFromServer.add({
              'id_jenis_tabungan': idVal,
              'nama': jenisName,
              'saldo': total,
            });
          }
        }
        _totalsByJenis = totals;
        _jenisList = jenisFromServer;
        // set selected id for currently selected jenis if available
        selectedIdJenisTabungan = _jenisList.firstWhere(
          (e) => e['nama'] == _selectedJenis,
          orElse: () => {'id_jenis_tabungan': null},
        )['id_jenis_tabungan'];
        print('DEBUG initial selectedIdJenisTabungan: $selectedIdJenisTabungan');

        // Update total tabungan from API sum (do not calculate on client)
        final tot = await EventDB.getTotalTabungan(idTab);
        if (tot != null) {
          _totalTabungan = tot;
        }

        // Ensure selected jenis remains valid
        final availableNames = _jenisList.map((e) => e['nama'].toString()).toList();
        if (!availableNames.contains(_selectedJenis) && availableNames.isNotEmpty) {
          _selectedJenis = availableNames.first;
        }

        // Fetch history for current selected jenis using mapped period
        final periodParam = _mapPeriodeToParam(_selectedPeriode);
        final apiPeriod = periodParam == 'today' ? '1' : periodParam;
        final hist = await EventDB.getRiwayatTabungan(
          idTab,
          _selectedJenis,
          apiPeriod,
        );
        _historyByJenis[_selectedJenis] = hist.map((m) => m).toList();
      }
    } catch (e) {
      if (kDebugMode) print('Load server data error: $e');
    }
    setState(() => _loading = false);
    // Start short polling for quick updates (useful when admin approves shortly after)
    if (_pollTimer == null) _startShortPoll();
  }

  // Fetch main user saldo from get_saldo_tabungan.php and update UI
  Future<void> _fetchSaldoUtama() async {
    try {
      final user = await EventPref.getUser();
      final idTab = user?.id ?? user?.no_hp ?? '';
      if (idTab.isEmpty) return;
      final resp = await EventDB.getSaldoTabungan(idTab);
      if (resp == null) return;
      if (resp['success'] == true && resp.containsKey('saldo')) {
        final s = int.tryParse(resp['saldo'].toString()) ?? 0;
        setState(() => _saldoUtama = s);
      }
    } catch (e) {
      if (kDebugMode) print('fetchSaldoUtama error: $e');
    }
  }

  // Targeted refresh used after a successful 'cairkan' to avoid full reloads
  Future<void> _refreshRincianAndSaldo() async {
    final user = await EventPref.getUser();
    final idTab = user?.id ?? user?.no_hp ?? '';
    if (idTab.isEmpty) return;
    // 1) Refresh authoritative saldo and local prefs
    await EventDB.refreshSaldoForCurrentUser();
    // 2) Fetch fresh rincian for the user
    final rinc = await EventDB.getRincianTabungan(idTab);
    // 3) Fetch tabungan list (server-side per-user data) but do not store globally
    final tabunganList = await EventDB.getTabungan(idTab);
    final Map<String, int> totals = {};
    final List<Map<String, dynamic>> jenisFromServer = [];
    final masterJenis = await EventDB.getJenisMaster();
    for (var r in rinc) {
      final jenisName = (r['jenis'] ?? '').toString();
      int? idVal = r['id'] != null ? int.tryParse(r['id'].toString()) : null;
      final total = int.tryParse((r['total'] ?? 0).toString()) ?? 0;
      if (idVal == null && jenisName.isNotEmpty && masterJenis.isNotEmpty) {
        final foundMaster = masterJenis.firstWhere(
          (m) => (m['nama'] ?? '').toString().toLowerCase() == jenisName.toLowerCase(),
          orElse: () => {},
        );
        if (foundMaster != null && (foundMaster is Map) && foundMaster.containsKey('id')) {
          idVal = foundMaster['id'];
        }
      }
      if (jenisName.isNotEmpty) {
        totals[jenisName] = total;
        jenisFromServer.add({
          'id_jenis_tabungan': idVal,
          'nama': jenisName,
          'saldo': total,
        });
      }
    }

    // 3) Also refresh transaction history for the currently selected jenis
    // Map selected periode to API param used elsewhere
    final periodParam = _mapPeriodeToParam(_selectedPeriode);
    final apiPeriod = periodParam == 'today' ? '1' : periodParam;
    List<Map<String, dynamic>> histList = [];
    try {
      final hist = await EventDB.getRiwayatTabungan(idTab, _selectedJenis, apiPeriod);
      histList = hist.map((m) => m).toList();
    } catch (e) {
      if (kDebugMode) print('refresh history error: $e');
    }

    // 4) Update UI state with minimal setState (do not cache in a global/static variable)
    setState(() {
      _totalsByJenis = totals;
      _jenisList = jenisFromServer;
      selectedIdJenisTabungan = _jenisList.firstWhere(
        (e) => e['nama'] == _selectedJenis,
        orElse: () => {'id_jenis_tabungan': null},
      )['id_jenis_tabungan'];
      // update history for the selected jenis so transaction list refreshes immediately
      _historyByJenis[_selectedJenis] = histList;
    });

    // 5) Update overall total and main saldo value
    final tot = await EventDB.getTotalTabungan(idTab);
    if (tot != null) setState(() => _totalTabungan = tot);
    try {
      await _fetchSaldoUtama();
    } catch (e) {
      if (kDebugMode) print('fetchSaldoUtama after refresh error: $e');
    }
  }

  String _mapPeriodeToParam(String p) {
    switch (p) {
      case 'Hari Sebelumnya':
        return 'today';
      case '7 Hari Terakhir':
        return '7';
      case '30 Hari Terakhir':
        return '30';
      case 'Tahun Lalu':
        return '365';
      default:
        return '30';
    }
  }

  // Normalize jenis name across the class (remove 'Tabungan' prefix and trim)
  String _normalizeJenis(String s) {
    return s.toString().toLowerCase().replaceAll(RegExp(r"\btabungan\b", caseSensitive: false), '').trim();
  }

  Future<void> _showCairkanDialog(String jenis, int available, {int? idJenis}) async {
    final user = await EventPref.getUser();
    final idTab = user?.id ?? user?.no_hp ?? '';
    if (idTab.isEmpty) return;

    final TextEditingController _nominalCtrl = TextEditingController();
    String _error = '';
    bool _isSubmitting = false; // guard to ensure 1 click = 1 request
    bool _isFormatting = false; // guard to prevent recursive formatting

    await showDialog<void>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx2, setState2) {
          return AlertDialog(
            title: Text('Cairkan: $jenis'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Saldo tersedia: ${CurrencyFormat.toIdr(available)}'),
                const SizedBox(height: 8),
                TextField(
                  controller: _nominalCtrl,
                  enabled: !_isSubmitting,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Masukan Nominal yang ingin dicairkan',
                    errorText: _error.isNotEmpty ? _error : null,
                  ),
                  onChanged: (value) {
                    if (_isFormatting) return;
                    _isFormatting = true;
                    // keep only digits
                    final digits = value.replaceAll(RegExp(r'[^0-9]'), '');
                    if (digits.isEmpty) {
                      setState2(() {
                        _nominalCtrl.text = '';
                        _nominalCtrl.selection = const TextSelection.collapsed(offset: 0);
                      });
                      _isFormatting = false;
                      return;
                    }
                    final parsed = int.tryParse(digits) ?? 0;
                    final formatted = CurrencyFormat.toIdr(parsed);
                    setState2(() {
                      _nominalCtrl.text = formatted;
                      _nominalCtrl.selection = TextSelection.collapsed(offset: _nominalCtrl.text.length);
                    });
                    _isFormatting = false;
                  },
                ),
              ],
            ),
            actions: [
              TextButton(onPressed: _isSubmitting ? null : () => Navigator.of(ctx).pop(), child: const Text('Batal')),
              ElevatedButton(
                onPressed: _isSubmitting ? null : () async {
                  // Prevent double submissions by checking and setting _isSubmitting
                  setState2(() { _isSubmitting = true; _error = ''; });

                  try {
                    final val = int.tryParse(_nominalCtrl.text.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
                    if (val <= 0) {
                      setState2(() { _isSubmitting = false; _error = 'Masukkan nominal yang valid'; });
                      return;
                    }
                    if (val > available) {
                      setState2(() { _isSubmitting = false; _error = 'Nominal melebihi saldo tersedia'; });
                      return;
                    }

                    // Determine idJenis to send. Prefer explicit param; otherwise use selectedIdJenisTabungan
                    int? idJenisTabungan = idJenis ?? selectedIdJenisTabungan;
                    if (idJenisTabungan == null) {
                      if (mounted) CustomToast.error(context, 'Jenis tabungan belum tersedia. Silakan refresh dan coba lagi.');
                      setState2(() { _isSubmitting = false; _error = 'Jenis tabungan belum tersedia'; });
                      return;
                    }

                    final ok = await EventDB.cairkanTabungan(idTab, jenis, val, idJenis: idJenisTabungan);
                    if (ok) {
                      Navigator.of(ctx).pop();
                      // NOTE: Success notification is handled by EventDB.cairkanTabungan to avoid duplicates.
                      // Refresh only the necessary data (saldo + rincian) and update UI without full page reload.
                      try {
                        await _refreshRincianAndSaldo();
                      } catch (e) {
                        if (kDebugMode) print('refresh after cairkan error: $e');
                        // As a fallback keep old behaviour: full reload
                        await _loadServerData();
                      }
                    } else {
                      setState2(() { _isSubmitting = false; _error = 'Gagal memproses. Silakan coba lagi'; });
                    }
                  } catch (e) {
                    setState2(() { _isSubmitting = false; _error = e?.toString() ?? 'Gagal memproses. Silakan coba lagi'; });
                  } finally {
                    // Ensure we re-enable controls if dialog is still open
                    try { setState2(() { _isSubmitting = false; }); } catch (_) {}
                  }
                },
                child: _isSubmitting ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.0, color: Colors.white)) : const Text('Konfirmasi', style: TextStyle(color: Colors.white)),
              ),
            ],
          );
        });
      },
    );
  }

  Future<void> _showCustomMenu(
    GlobalKey key,
    List<String> options,
    String current,
    ValueChanged<String> onSelected,
  ) async {
    final theme = Theme.of(context);
    final RenderBox box = key.currentContext!.findRenderObject() as RenderBox;
    final Offset topLeft = box.localToGlobal(Offset.zero);
    final RelativeRect position = RelativeRect.fromLTRB(
      topLeft.dx,
      topLeft.dy + box.size.height,
      topLeft.dx + box.size.width,
      0,
    );

    final result = await showMenu<String>(
      context: context,
      position: position,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      color: theme.cardColor,
      elevation: 8,
      items: options.map((opt) {
        final bool selected = opt == current;
        return PopupMenuItem<String>(
          value: opt,
          child: Container(
            width: box.size.width,
            padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
            decoration: BoxDecoration(
              color: selected
                  ? theme.cardColor.withOpacity(0.7)
                  : theme.cardColor,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              opt,
              style: GoogleFonts.roboto(
                color: selected
                    ? theme.textTheme.bodyLarge?.color
                    : theme.textTheme.bodyMedium?.color,
              ),
            ),
          ),
        );
      }).toList(),
    );

    if (result != null) onSelected(result);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: const Color(0xFFFF4C00),
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () {
            if (widget.onBackToDashboard != null) {
              widget.onBackToDashboard!();
            } else {
              Navigator.of(context).pop();
            }
          },
        ),
        title: Text(
          'Halaman Tabungan',
          style: GoogleFonts.roboto(color: Colors.white),
        ),
        centerTitle: true,
      ),
      body: RefreshIndicator(
        onRefresh: _loadServerData,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // small inline loader
              if (_loading) const LinearProgressIndicator(minHeight: 3),
              const SizedBox(height: 16),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.person, color: Color(0xFFFF4C00)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _userName,
                            style: GoogleFonts.roboto(
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          Text(
                            _userRole.isNotEmpty ? _userRole : '',
                            style: GoogleFonts.roboto(
                              fontSize: 12,
                              color: Colors.grey[600],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 12),
              // Total Tabungan card
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF4C00),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Total Tabungan',
                        style: GoogleFonts.roboto(
                          color: Colors.white,
                          fontSize: 12,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        CurrencyFormat.toIdr(_totalTabungan),
                        style: GoogleFonts.roboto(
                          color: Colors.white,
                          fontSize: 24,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        DateFormat('MMMM yyyy', 'id').format(DateTime.now()),
                        style: GoogleFonts.roboto(
                          color: Colors.white70,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 16),
              // Riwayat tabungan card
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.04),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Text(
                          'Rincian tabungan',
                          style: GoogleFonts.roboto(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const Divider(height: 1),
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: _jenisList.map((item) {
                            final String name = (item['nama'] ?? '').toString();
                            // Debug: show the item id_jenis_tabungan from the server-supplied list
                            print("DEBUG item id_jenis_tabungan: ${item['id_jenis_tabungan']}");
                            final int? idVal = item['id_jenis_tabungan'] != null ? int.tryParse(item['id_jenis_tabungan'].toString()) : null;
                            final total = _totalsByJenis[name] ?? 0;
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8.0),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      name,
                                      style: GoogleFonts.roboto(
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    CurrencyFormat.toIdr(total),
                                    style: GoogleFonts.roboto(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  // Cairkan button (disabled when total == 0)
                                  TextButton(
                                    onPressed: total > 0 ? () async {
                                      // Set selectedIdJenisTabungan immediately when the Cairkan button is pressed
                                      setState(() {
                                        selectedIdJenisTabungan = idVal;
                                      });
                                      print("DEBUG SET selectedIdJenisTabungan from list: $selectedIdJenisTabungan");

                                      // Always open dialog; validation of selectedIdJenisTabungan happens on 'Konfirmasi' inside the dialog
                                      _showCairkanDialog(name, total);
                                    } : null,
                                    child: const Text('Cairkan'),
                                  ),
                                ],
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 16),
              // Filters section (Jenis Tabungan, Periode)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Jenis Tabungan',
                            style: GoogleFonts.roboto(
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 8),
                          // custom styled dropdown trigger
                          GestureDetector(
                            key: _jenisKey,
                            onTap: () => _showCustomMenu(
                              _jenisKey,
                              _jenisList.map((e) => e['nama'].toString()).toList(),
                              _selectedJenis,
                              (v) async {
                                setState(() => _selectedJenis = v);
                                // fetch history for selected jenis with current periode
                                final user = await EventPref.getUser();
                                final idTab = user?.id ?? user?.no_hp ?? '';
                                if (idTab.isNotEmpty) {
                                  setState(() => _loading = true);
                                  final periodParam = _mapPeriodeToParam(_selectedPeriode);
                                  final apiPeriod = periodParam == 'today' ? '1' : periodParam;
                                  final hist = await EventDB.getRiwayatTabungan(
                                    idTab,
                                    v,
                                    apiPeriod,
                                  );

                                  // Update history and loading state
                                  setState(() {
                                    _historyByJenis[v] = hist.map((m) => m).toList();
                                    _loading = false;
                                  });

                                  // Find selected jenis id from the available _jenisList
                                  final found = _jenisList.firstWhere(
                                    (e) => e['nama'] == v,
                                    orElse: () => {'id_jenis_tabungan': null},
                                  );
                                  selectedIdJenisTabungan = found['id_jenis_tabungan'];

                                  // Debug print mapping for selected jenis
                                  print('DEBUG selected jenis: $v, mapped id: ${selectedIdJenisTabungan}');
                                }
                              },
                            ),
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 12,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withOpacity(0.03),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      _selectedJenis,
                                      style: GoogleFonts.roboto(),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  const Icon(Icons.arrow_drop_down),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Periode Tanggal',
                            style: GoogleFonts.roboto(
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 8),
                          GestureDetector(
                            key: _periodeKey,
                            onTap: () => _showCustomMenu(
                              _periodeKey,
                              _periodeOptions,
                              _selectedPeriode,
                              (v) async {
                                setState(() => _selectedPeriode = v);
                                // refetch history for selected jenis using new periode
                                final user = await EventPref.getUser();
                                final idTab = user?.id ?? user?.no_hp ?? '';
                                if (idTab.isNotEmpty) {
                                  setState(() => _loading = true);
                                  final periodParam = _mapPeriodeToParam(v);
                                  final apiPeriod = periodParam == 'today' ? '1' : periodParam;
                                  final hist = await EventDB.getRiwayatTabungan(
                                    idTab,
                                    _selectedJenis,
                                    apiPeriod,
                                  );
                                  setState(() {
                                    _historyByJenis[_selectedJenis] = hist
                                        .map((m) => m)
                                        .toList();
                                    _loading = false;
                                  });
                                }
                              },
                            ),
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 12,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withOpacity(0.03),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      _selectedPeriode,
                                      style: GoogleFonts.roboto(),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  const Icon(Icons.arrow_drop_down),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              // Riwayat Tabungan (filtered by selected jenis)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.04),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Text(
                          'Riwayat Tabungan',
                          style: GoogleFonts.roboto(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const Divider(height: 1),
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            if ((_historyByJenis[_selectedJenis] ?? [])
                                .isNotEmpty)
                              ...((_historyByJenis[_selectedJenis] ?? []).map((
                                e,
                              ) {
                                final int amt = (e['jumlah'] is int)
                                    ? e['jumlah'] as int
                                    : int.tryParse('${e['jumlah']}') ?? 0;
                                final String tanggal = e['tanggal'] ?? '-';
                                final String jenisTitle = (e['jenis_tabungan'] ?? e['jenis'] ?? '-') .toString();
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 8.0),
                                  child: _buildRiwayatRow(
                                    tanggal,
                                    jenisTitle,
                                    amt,
                                  ),
                                );
                              }).toList())
                            else
                              Text(
                                'Belum ada riwayat untuk jenis ini',
                                style: GoogleFonts.roboto(),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 80),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildRiwayatRow(String date, String title, int amount) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.roboto(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 4),
              Text(
                date,
                style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey),
              ),
            ],
          ),
        ),
        Text(
          CurrencyFormat.toIdr(amount),
          style: GoogleFonts.roboto(fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}
