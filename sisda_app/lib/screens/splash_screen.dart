import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../features/auth/auth_controller.dart';
import '../theme/app_theme.dart';
import '../providers.dart';
import 'login_screen.dart';
import 'home_screen.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(duration: const Duration(milliseconds: 1500), vsync: this);
    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));
    _controller.forward();
    _checkSession();
  }

  Future<void> _checkSession() async {
    // authControllerProvider's build() restores any saved session from
    // shared_preferences; wait for it alongside the minimum splash delay.
    final results = await Future.wait([
      Future.delayed(const Duration(seconds: 3)),
      ref.read(authControllerProvider.future),
    ]);
    if (!mounted) return;

    final authState = results[1] as AuthState;
    if (authState.loggedIn) {
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const HomeScreen()));
    } else {
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppTheme.primary, Color(0xFF1e3a5f), AppTheme.primary],
          ),
        ),
        child: Center(
          child: FadeTransition(
            opacity: _fadeAnimation,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 120,
                  height: 120,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 20, offset: const Offset(0, 8))],
                  ),
                  child: ClipOval(child: Padding(padding: const EdgeInsets.all(16), child: Image.asset('assets/images/logo-sisda.png', fit: BoxFit.contain))),
                ),
                const SizedBox(height: 32),
                const Text('SISDA', style: TextStyle(fontSize: 42, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 6)),
                const SizedBox(height: 8),
                Text('Sistem Data Pengundi', style: TextStyle(fontSize: 16, color: Colors.white.withValues(alpha: 0.7), letterSpacing: 1.5)),
                const SizedBox(height: 48),
                SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white.withValues(alpha: 0.5)))),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
