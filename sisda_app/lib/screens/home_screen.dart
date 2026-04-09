import 'package:flutter/material.dart';
import 'dart:io';
import 'package:image_picker/image_picker.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import '../services/ocr_service.dart';
import 'webview_screen.dart';
import 'login_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  bool _isScanning = false;

  final List<_NavItem> _navItems = [
    _NavItem('Dashboard', Icons.dashboard_rounded, '/dashboard'),
    _NavItem('Culaan', Icons.assignment_rounded, '/reports/hasil-culaan/create'),
    _NavItem('Pengundi', Icons.person_add_rounded, '/reports/data-pengundi/create'),
    _NavItem('Laporan', Icons.bar_chart_rounded, '/reports/hasil-culaan'),
    _NavItem('Profil', Icons.person_rounded, '/profile'),
  ];

  Future<void> _scanKP() async {
    setState(() => _isScanning = true);
    try {
      final picker = ImagePicker();
      final image = await picker.pickImage(source: ImageSource.camera, imageQuality: 90);
      if (image == null) { setState(() => _isScanning = false); return; }

      final kpData = await OcrService.extractFromImage(File(image.path));

      if (kpData.icNumber != null && mounted) {
        _showKpResult(kpData);
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Tidak dapat membaca No. IC dari gambar. Sila cuba lagi.')));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Ralat: $e')));
    } finally {
      if (mounted) setState(() => _isScanning = false);
    }
  }

  void _showKpResult(KpData kpData) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)))),
          const SizedBox(height: 20),
          const Text('Maklumat KP', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          _infoRow('No. IC', kpData.icNumber ?? '-'),
          if (kpData.name != null) _infoRow('Nama', kpData.name!),
          if (kpData.address != null) _infoRow('Alamat', kpData.address!),
          const SizedBox(height: 24),
          const Text('Pilih Borang:', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: ElevatedButton.icon(
              onPressed: () { Navigator.pop(ctx); _openWebView('/reports/hasil-culaan/create?ic=${kpData.icNumber}'); },
              icon: const Icon(Icons.assignment), label: const Text('Borang Culaan'),
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF059669), padding: const EdgeInsets.symmetric(vertical: 14)),
            )),
            const SizedBox(width: 12),
            Expanded(child: ElevatedButton.icon(
              onPressed: () { Navigator.pop(ctx); _openWebView('/reports/data-pengundi/create?ic=${kpData.icNumber}'); },
              icon: const Icon(Icons.person_add), label: const Text('Data Pengundi'),
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.accent, padding: const EdgeInsets.symmetric(vertical: 14)),
            )),
          ]),
          const SizedBox(height: 16),
        ]),
      ),
    );
  }

  Widget _infoRow(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 70, child: Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13))),
      Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15))),
    ]),
  );

  void _openWebView(String path) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => WebViewScreen(path: path)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(children: [
        // KP Scanner Card
        Container(
          padding: EdgeInsets.only(top: MediaQuery.of(context).padding.top + 16, left: 16, right: 16, bottom: 16),
          decoration: const BoxDecoration(
            gradient: LinearGradient(colors: [AppTheme.primary, Color(0xFF1e3a5f)]),
            borderRadius: BorderRadius.only(bottomLeft: Radius.circular(24), bottomRight: Radius.circular(24)),
          ),
          child: Column(children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text('SISDA', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: 3)),
              IconButton(icon: const Icon(Icons.logout_rounded, color: Colors.white), onPressed: () async {
                await ApiService.logout();
                if (mounted) Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
              }),
            ]),
            const SizedBox(height: 12),
            GestureDetector(
              onTap: _isScanning ? null : _scanKP,
              child: Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(16), border: Border.all(color: Colors.white.withValues(alpha: 0.2))),
                child: Row(children: [
                  Container(
                    width: 56, height: 56,
                    decoration: BoxDecoration(color: AppTheme.accent, borderRadius: BorderRadius.circular(14)),
                    child: _isScanning
                      ? const Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 28),
                  ),
                  const SizedBox(width: 16),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Text('Imbas Kad Pengenalan', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    Text('Ambil gambar KP untuk carian pantas', style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 13)),
                  ])),
                  Icon(Icons.chevron_right, color: Colors.white.withValues(alpha: 0.5)),
                ]),
              ),
            ),
          ]),
        ),

        // WebView content
        Expanded(child: WebViewScreen(path: _navItems[_currentIndex].path, embedded: true)),
      ]),

      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (i) => setState(() => _currentIndex = i),
        destinations: _navItems.map((item) => NavigationDestination(icon: Icon(item.icon), label: item.label)).toList(),
      ),
    );
  }
}

class _NavItem {
  final String label;
  final IconData icon;
  final String path;
  _NavItem(this.label, this.icon, this.path);
}
