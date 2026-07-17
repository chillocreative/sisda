import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers.dart';
import '../culaan/rekod_saya_screen.dart';
import '../home/home_screen.dart';
import '../perlu_perhatian/perlu_perhatian_screen.dart';
import '../sync/draft_counts_provider.dart';
import '../webview/web_screens.dart';

const _labelUtama = 'Utama';
const _labelCulaan = 'Culaan';
const _labelPerluPerhatian = 'Perlu Perhatian';
const _labelProfil = 'Profil';
const _labelDashboard = 'Dashboard';
const _labelLaporan = 'Laporan';
const _labelDataPengundi = 'Data Pengundi';

/// The app's logged-in root: a 4-tab bottom nav shell over Utama, Culaan,
/// Perlu Perhatian and Profil, preserving each tab's state via
/// IndexedStack. Also the root WidgetsBindingObserver that drains the sync
/// queue whenever the app returns to the foreground.
class MainShell extends ConsumerStatefulWidget {
  const MainShell({super.key});

  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell> with WidgetsBindingObserver {
  int _selectedIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Wiring layer only — DateTime.now() must never leak into sync/.
      ref.read(syncEngineProvider).syncNow(now: DateTime.now());
    }
  }

  @override
  Widget build(BuildContext context) {
    final failedCount = ref.watch(draftCountsProvider).valueOrNull?.failed;

    return Scaffold(
      body: IndexedStack(
        index: _selectedIndex,
        children: const [
          HomeScreen(),
          RekodSayaScreen(),
          PerluPerhatianScreen(),
          _ProfilTab(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (index) => setState(() => _selectedIndex = index),
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: _labelUtama,
          ),
          const NavigationDestination(
            icon: Icon(Icons.assignment_outlined),
            selectedIcon: Icon(Icons.assignment),
            label: _labelCulaan,
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: failedCount != null && failedCount > 0,
              label: Text('$failedCount'),
              child: const Icon(Icons.warning_amber_outlined),
            ),
            selectedIcon: Badge(
              isLabelVisible: failedCount != null && failedCount > 0,
              label: Text('$failedCount'),
              child: const Icon(Icons.warning_amber),
            ),
            label: _labelPerluPerhatian,
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: _labelProfil,
          ),
        ],
      ),
    );
  }
}

/// The Profil tab: the "WebView tail" entry point. It never embeds
/// `WebViewScreen` directly in the tree — the IndexedStack builds all four
/// tab bodies up front, and `WebViewScreen`'s `WebViewController` needs a
/// platform channel unavailable under `flutter_test`. Instead each row
/// `Navigator.push`es a `WebViewScreen` on demand via the `web_screens.dart`
/// helpers, so the heavy web screens (Dashboard, Laporan, Data Pengundi,
/// Profil) stay reachable without ever being mounted eagerly.
class _ProfilTab extends StatelessWidget {
  const _ProfilTab();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text(_labelProfil)),
      body: ListView(
        children: [
          ListTile(
            leading: const Icon(Icons.person_outline),
            title: const Text(_labelProfil),
            onTap: () => openProfil(context),
          ),
          const Divider(height: 1),
          ListTile(
            leading: const Icon(Icons.dashboard_outlined),
            title: const Text(_labelDashboard),
            onTap: () => openDashboard(context),
          ),
          ListTile(
            leading: const Icon(Icons.bar_chart_outlined),
            title: const Text(_labelLaporan),
            onTap: () => openLaporan(context),
          ),
          ListTile(
            leading: const Icon(Icons.groups_outlined),
            title: const Text(_labelDataPengundi),
            onTap: () => openDataPengundi(context),
          ),
        ],
      ),
    );
  }
}
