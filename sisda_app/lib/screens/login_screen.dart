import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import 'home_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _telephoneController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  bool _obscurePassword = true;
  String? _errorMessage;

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _isLoading = true; _errorMessage = null; });
    try {
      final result = await ApiService.login(_telephoneController.text.trim(), _passwordController.text);
      if (result['success'] == true && mounted) {
        Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const HomeScreen()));
      } else {
        setState(() { _errorMessage = result['errors']?['telephone']?[0] ?? 'Log masuk gagal.'; });
      }
    } catch (e) {
      setState(() { _errorMessage = 'Ralat sambungan. Sila semak internet.'; });
    } finally {
      if (mounted) setState(() { _isLoading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [AppTheme.primary, Color(0xFF1e3a5f)], stops: [0.0, 0.4])),
        child: SafeArea(child: Center(child: SingleChildScrollView(padding: const EdgeInsets.all(24), child: Column(children: [
          Container(width: 80, height: 80, decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: 0.15)), child: const Icon(Icons.how_to_vote_rounded, size: 40, color: Colors.white)),
          const SizedBox(height: 16),
          const Text('SISDA', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 4)),
          const SizedBox(height: 32),
          Container(padding: const EdgeInsets.all(24), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 20, offset: const Offset(0, 10))]),
            child: Form(key: _formKey, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const Text('Selamat Kembali', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppTheme.textPrimary)),
              const SizedBox(height: 4),
              const Text('Sila masukkan butiran anda', style: TextStyle(color: AppTheme.textSecondary)),
              const SizedBox(height: 24),
              if (_errorMessage != null) Container(padding: const EdgeInsets.all(12), margin: const EdgeInsets.only(bottom: 16), decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.red.shade200)), child: Text(_errorMessage!, style: TextStyle(color: Colors.red.shade700, fontSize: 13))),
              TextFormField(controller: _telephoneController, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Nombor Telefon', prefixIcon: Icon(Icons.phone_outlined)), validator: (v) => v == null || v.isEmpty ? 'Sila masukkan nombor telefon' : null),
              const SizedBox(height: 16),
              TextFormField(controller: _passwordController, obscureText: _obscurePassword, decoration: InputDecoration(labelText: 'Kata Laluan', prefixIcon: const Icon(Icons.lock_outlined), suffixIcon: IconButton(icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _obscurePassword = !_obscurePassword))), validator: (v) => v == null || v.isEmpty ? 'Sila masukkan kata laluan' : null),
              const SizedBox(height: 24),
              SizedBox(height: 50, child: ElevatedButton(onPressed: _isLoading ? null : _login, child: _isLoading ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Log Masuk'))),
            ])),
          ),
        ])))),
      ),
    );
  }

  @override
  void dispose() { _telephoneController.dispose(); _passwordController.dispose(); super.dispose(); }
}
