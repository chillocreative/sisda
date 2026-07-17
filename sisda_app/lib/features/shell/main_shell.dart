import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers.dart';
import '../home/home_screen.dart';
import '../perlu_perhatian/perlu_perhatian_screen.dart';
import '../sync/draft_counts_provider.dart';

const _labelUtama = 'Utama';
const _labelCulaan = 'Culaan';
const _labelPerluPerhatian = 'Perlu Perhatian';
const _labelProfil = 'Profil';

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
          _CulaanTab(),
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

/// Placeholder — a later task (Plan 2b-ii / the Culaan list task) replaces
/// this body with the real Culaan tab (list + entry points into
/// CulaanFormScreen).
class _CulaanTab extends StatelessWidget {
  const _CulaanTab();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text(_labelCulaan)),
      body: const Center(
        child: Text('Senarai Culaan akan tersedia tidak lama lagi.'),
      ),
    );
  }
}

/// Placeholder — deliberately NOT wired to WebViewScreen(path: '/profile')
/// here. WebViewController's platform channel isn't available under
/// flutter_test (no WebViewPlatform.instance registered), so embedding it
/// directly in MainShell would make every shell widget test require a
/// platform mock. Task 8's WebView helper is expected to fill this body in
/// (and to supply the test-time platform stub if it adds coverage there).
class _ProfilTab extends StatelessWidget {
  const _ProfilTab();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text(_labelProfil)),
      body: const Center(
        child: Text('Profil akan tersedia tidak lama lagi.'),
      ),
    );
  }
}
