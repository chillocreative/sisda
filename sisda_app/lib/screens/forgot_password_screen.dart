import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _telephoneController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;
  String? _successMessage;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _isLoading = true; _errorMessage = null; _successMessage = null; });

    try {
      final result = await ApiService.forgotPassword(_telephoneController.text.trim());
      if (result['success'] == true) {
        final msg = result['message'] ?? 'Kata laluan baharu telah dihantar ke WhatsApp anda.';
        setState(() { _successMessage = msg; });
      } else {
        setState(() { _errorMessage = result['errors']?['telephone']?[0] ?? 'Nombor telefon tidak dijumpai.'; });
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
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [AppTheme.primary, Color(0xFF1e3a5f)],
            stops: [0.0, 0.4],
          ),
        ),
        child: SafeArea(
          child: Column(children: [
            // Back button
            Align(
              alignment: Alignment.centerLeft,
              child: IconButton(
                icon: const Icon(Icons.arrow_back, color: Colors.white),
                onPressed: () => Navigator.pop(context),
              ),
            ),

            Expanded(
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(24),
                  child: Column(children: [
                    // Logo
                    Container(
                      width: 90,
                      height: 90,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.white,
                        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 16, offset: const Offset(0, 6))],
                      ),
                      child: ClipOval(child: Padding(padding: const EdgeInsets.all(14), child: Image.asset('assets/images/logo-sisda.png', fit: BoxFit.contain))),
                    ),
                    const SizedBox(height: 16),
                    const Text('SISDA', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 4)),
                    const SizedBox(height: 32),

                    // Card
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(20),
                        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 20, offset: const Offset(0, 10))],
                      ),
                      child: Form(
                        key: _formKey,
                        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                          const Text('Lupa Kata Laluan', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppTheme.textPrimary)),
                          const SizedBox(height: 8),
                          const Text(
                            'Masukkan nombor telefon anda. Kata laluan baharu akan dihantar melalui WhatsApp.',
                            style: TextStyle(color: AppTheme.textSecondary, fontSize: 13, height: 1.4),
                          ),
                          const SizedBox(height: 24),

                          if (_errorMessage != null)
                            Container(
                              padding: const EdgeInsets.all(12),
                              margin: const EdgeInsets.only(bottom: 16),
                              decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.red.shade200)),
                              child: Text(_errorMessage!, style: TextStyle(color: Colors.red.shade700, fontSize: 13)),
                            ),

                          if (_successMessage != null)
                            Container(
                              padding: const EdgeInsets.all(12),
                              margin: const EdgeInsets.only(bottom: 16),
                              decoration: BoxDecoration(color: Colors.green.shade50, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.green.shade200)),
                              child: Row(children: [
                                Icon(Icons.check_circle, color: Colors.green.shade700, size: 20),
                                const SizedBox(width: 8),
                                Expanded(child: Text(_successMessage!, style: TextStyle(color: Colors.green.shade700, fontSize: 13))),
                              ]),
                            ),

                          TextFormField(
                            controller: _telephoneController,
                            keyboardType: TextInputType.phone,
                            decoration: const InputDecoration(labelText: 'Nombor Telefon', prefixIcon: Icon(Icons.phone_outlined)),
                            validator: (v) => v == null || v.isEmpty ? 'Sila masukkan nombor telefon' : null,
                          ),
                          const SizedBox(height: 24),

                          SizedBox(
                            height: 50,
                            child: ElevatedButton(
                              onPressed: _isLoading ? null : _submit,
                              child: _isLoading
                                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                : const Text('Hantar Kata Laluan Baharu'),
                            ),
                          ),

                          const SizedBox(height: 16),
                          // Info about WhatsApp
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFBBF7D0))),
                            child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Icon(Icons.info_outline, color: Color(0xFF16A34A), size: 18),
                              SizedBox(width: 8),
                              Expanded(child: Text(
                                'Kata laluan baharu akan dihantar ke WhatsApp yang berdaftar dengan nombor telefon ini.',
                                style: TextStyle(color: Color(0xFF166534), fontSize: 12, height: 1.4),
                              )),
                            ]),
                          ),
                        ]),
                      ),
                    ),
                  ]),
                ),
              ),
            ),
          ]),
        ),
      ),
    );
  }

  @override
  void dispose() { _telephoneController.dispose(); super.dispose(); }
}
