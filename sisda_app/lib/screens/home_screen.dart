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
  bool _inWebView = false;
  String? _webViewPath;

  final List<_NavItem> _navItems = [
    _NavItem('Dashboard', Icons.dashboard_rounded, '/dashboard'),
    _NavItem('Culaan', Icons.assignment_rounded, '/reports/hasil-culaan'),
    _NavItem('Pengundi', Icons.people_rounded, '/reports/data-pengundi'),
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
              onPressed: () { Navigator.pop(ctx); _openForm('/reports/hasil-culaan/create?ic=${kpData.icNumber}'); },
              icon: const Icon(Icons.assignment), label: const Text('Borang Culaan'),
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF059669), padding: const EdgeInsets.symmetric(vertical: 14)),
            )),
            const SizedBox(width: 12),
            Expanded(child: ElevatedButton.icon(
              onPressed: () { Navigator.pop(ctx); _openForm('/reports/data-pengundi/create?ic=${kpData.icNumber}'); },
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

  void _openForm(String path) {
    setState(() { _inWebView = true; _webViewPath = path; });
  }

  void _goToNav(int index) {
    setState(() {
      _currentIndex = index;
      _inWebView = true;
      _webViewPath = _navItems[index].path;
    });
  }

  void _backToHome() {
    setState(() { _inWebView = false; _webViewPath = null; });
  }

  @override
  Widget build(BuildContext context) {
    // If in WebView mode, show full-screen webview with back button
    if (_inWebView && _webViewPath != null) {
      return Scaffold(
        appBar: AppBar(
          backgroundColor: AppTheme.primary,
          leading: IconButton(icon: const Icon(Icons.arrow_back, color: Colors.white), onPressed: _backToHome),
          title: const Text('SISDA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, letterSpacing: 2)),
          actions: [
            IconButton(
              icon: const Icon(Icons.logout_rounded, color: Colors.white),
              onPressed: () async {
                await ApiService.logout();
                if (mounted) Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
              },
            ),
          ],
        ),
        body: WebViewScreen(path: _webViewPath!, embedded: true),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _currentIndex,
          onDestinationSelected: _goToNav,
          destinations: _navItems.map((item) => NavigationDestination(icon: Icon(item.icon), label: item.label)).toList(),
        ),
      );
    }

    // Home screen: Camera scanner + 2 form buttons
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [AppTheme.primary, Color(0xFF1e3a5f)],
            stops: [0.0, 0.5],
          ),
        ),
        child: SafeArea(
          child: Column(children: [
            // Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('SISDA', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: 3)),
                IconButton(
                  icon: const Icon(Icons.logout_rounded, color: Colors.white),
                  onPressed: () async {
                    await ApiService.logout();
                    if (mounted) Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
                  },
                ),
              ]),
            ),

            Expanded(
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(children: [
                    // Logo
                    Container(
                      width: 100,
                      height: 100,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.white,
                        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 20, offset: const Offset(0, 8))],
                      ),
                      child: ClipOval(child: Padding(padding: const EdgeInsets.all(16), child: Image.asset('assets/images/logo-sisda.png', fit: BoxFit.contain))),
                    ),
                    const SizedBox(height: 24),

                    // Camera Scanner Card
                    GestureDetector(
                      onTap: _isScanning ? null : _scanKP,
                      child: Container(
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
                        ),
                        child: Column(children: [
                          Container(
                            width: 72,
                            height: 72,
                            decoration: BoxDecoration(color: AppTheme.accent, borderRadius: BorderRadius.circular(18)),
                            child: _isScanning
                              ? const Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator(strokeWidth: 3, color: Colors.white))
                              : const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 36),
                          ),
                          const SizedBox(height: 16),
                          const Text('Imbas Kad Pengenalan', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          Text('Ambil gambar KP untuk carian pantas', style: TextStyle(color: Colors.white.withValues(alpha: 0.6), fontSize: 13)),
                        ]),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Two Form Buttons
                    Row(children: [
                      Expanded(
                        child: _formButton(
                          icon: Icons.assignment_rounded,
                          label: 'Borang\nCulaan',
                          color: const Color(0xFF059669),
                          onTap: () => _openForm('/reports/hasil-culaan/create'),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: _formButton(
                          icon: Icons.person_add_rounded,
                          label: 'Borang\nData Pengundi',
                          color: AppTheme.accent,
                          onTap: () => _openForm('/reports/data-pengundi/create'),
                        ),
                      ),
                    ]),
                    const SizedBox(height: 32),
                  ]),
                ),
              ),
            ),

            // Bottom Navigation
            NavigationBar(
              selectedIndex: _currentIndex,
              onDestinationSelected: _goToNav,
              destinations: _navItems.map((item) => NavigationDestination(icon: Icon(item.icon), label: item.label)).toList(),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _formButton({required IconData icon, required String label, required Color color, required VoidCallback onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [BoxShadow(color: color.withValues(alpha: 0.4), blurRadius: 12, offset: const Offset(0, 6))],
        ),
        child: Column(children: [
          Icon(icon, color: Colors.white, size: 36),
          const SizedBox(height: 12),
          Text(label, style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700, height: 1.3), textAlign: TextAlign.center),
        ]),
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
