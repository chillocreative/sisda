import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../theme/app_theme.dart';
import '../providers.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});
  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _telephoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _isLoading = false;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  String? _errorMessage;

  List<dynamic> _negeriList = [];
  List<dynamic> _bandarList = [];
  List<dynamic> _kadunList = [];
  int? _selectedNegeri;
  int? _selectedBandar;
  int? _selectedKadun;

  @override
  void initState() {
    super.initState();
    _loadNegeriList();
  }

  Future<void> _loadNegeriList() async {
    try {
      final list = await ref.read(mobileApiProvider).negeriList();
      if (mounted) setState(() => _negeriList = list);
    } catch (_) {
      // dropdown population is best-effort; leave list empty on failure
    }
  }

  Future<void> _onNegeriChanged(int? val) async {
    setState(() { _selectedNegeri = val; _selectedBandar = null; _selectedKadun = null; _bandarList = []; _kadunList = []; });
    if (val != null) {
      try {
        final list = await ref.read(mobileApiProvider).bandarByNegeri(val);
        if (mounted) setState(() => _bandarList = list);
      } catch (_) {}
    }
  }

  Future<void> _onBandarChanged(int? val) async {
    setState(() { _selectedBandar = val; _selectedKadun = null; _kadunList = []; });
    if (val != null) {
      try {
        final list = await ref.read(mobileApiProvider).kadunByBandar(val);
        if (mounted) setState(() => _kadunList = list);
      } catch (_) {}
    }
  }

  Future<void> _register() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedNegeri == null || _selectedBandar == null || _selectedKadun == null) {
      setState(() => _errorMessage = 'Sila pilih Negeri, Bandar dan DUN.');
      return;
    }

    setState(() { _isLoading = true; _errorMessage = null; });

    try {
      await ref.read(authControllerProvider.notifier).register({
        'name': _nameController.text.trim(),
        'telephone': _telephoneController.text.trim(),
        if (_emailController.text.trim().isNotEmpty) 'email': _emailController.text.trim(),
        'password': _passwordController.text,
        'password_confirmation': _confirmPasswordController.text,
        'negeri_id': _selectedNegeri,
        'bandar_id': _selectedBandar,
        'kadun_id': _selectedKadun,
      });

      final authState = ref.read(authControllerProvider).value;
      if (authState?.errorMessage == null && mounted) {
        _showSuccessDialog();
      } else {
        setState(() { _errorMessage = authState?.errorMessage; });
      }
    } catch (e) {
      setState(() { _errorMessage = 'Ralat sambungan. Sila semak internet.'; });
    } finally {
      if (mounted) setState(() { _isLoading = false; });
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(children: [
          Icon(Icons.check_circle, color: Color(0xFF16A34A), size: 28),
          SizedBox(width: 8),
          Text('Berjaya!'),
        ]),
        content: const Text('Pendaftaran berjaya! Sila tunggu kelulusan daripada pentadbir sebelum anda boleh log masuk.', style: TextStyle(height: 1.4)),
        actions: [
          ElevatedButton(
            onPressed: () { Navigator.pop(ctx); Navigator.pop(context); },
            child: const Text('Kembali ke Log Masuk'),
          ),
        ],
      ),
    );
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
            stops: [0.0, 0.3],
          ),
        ),
        child: SafeArea(
          child: Column(children: [
            // Back button
            Align(
              alignment: Alignment.centerLeft,
              child: IconButton(icon: const Icon(Icons.arrow_back, color: Colors.white), onPressed: () => Navigator.pop(context)),
            ),

            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Column(children: [
                  // Logo
                  Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white,
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 16, offset: const Offset(0, 6))],
                    ),
                    child: ClipOval(child: Padding(padding: const EdgeInsets.all(12), child: Image.asset('assets/images/logo-sisda.png', fit: BoxFit.contain))),
                  ),
                  const SizedBox(height: 12),
                  const Text('SISDA', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 4)),
                  const SizedBox(height: 24),

                  // Register Card
                  Container(
                    padding: const EdgeInsets.all(24),
                    margin: const EdgeInsets.only(bottom: 32),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 20, offset: const Offset(0, 10))],
                    ),
                    child: Form(
                      key: _formKey,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        const Text('Cipta Akaun', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppTheme.textPrimary)),
                        const SizedBox(height: 4),
                        const Text('Masukkan butiran anda untuk bermula.', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                        const SizedBox(height: 16),

                        // Info box
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: const Color(0xFFF0F9FF), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFBAE6FD))),
                          child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Icon(Icons.info_outline_rounded, color: Color(0xFF38BDF8), size: 18),
                            SizedBox(width: 8),
                            Expanded(child: Text(
                              'Akaun anda akan menunggu kelulusan daripada pentadbir sebelum anda boleh log masuk.',
                              style: TextStyle(fontSize: 12, color: Color(0xFF0369A1), height: 1.4),
                            )),
                          ]),
                        ),
                        const SizedBox(height: 20),

                        if (_errorMessage != null)
                          Container(
                            padding: const EdgeInsets.all(12),
                            margin: const EdgeInsets.only(bottom: 16),
                            decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.red.shade200)),
                            child: Text(_errorMessage!, style: TextStyle(color: Colors.red.shade700, fontSize: 13)),
                          ),

                        // Name
                        TextFormField(
                          controller: _nameController,
                          decoration: const InputDecoration(labelText: 'Nama', prefixIcon: Icon(Icons.person_outlined)),
                          validator: (v) => v == null || v.isEmpty ? 'Sila masukkan nama' : null,
                        ),
                        const SizedBox(height: 14),

                        // Telephone
                        TextFormField(
                          controller: _telephoneController,
                          keyboardType: TextInputType.phone,
                          decoration: const InputDecoration(labelText: 'Nombor Telefon', prefixIcon: Icon(Icons.phone_outlined), hintText: '0123456789'),
                          validator: (v) => v == null || v.isEmpty ? 'Sila masukkan nombor telefon' : null,
                        ),
                        const SizedBox(height: 14),

                        // Email
                        TextFormField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          decoration: const InputDecoration(labelText: 'Email (Pilihan)', prefixIcon: Icon(Icons.email_outlined)),
                        ),
                        const SizedBox(height: 20),

                        // Territory section
                        const Text('Kawasan Anda', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppTheme.textPrimary)),
                        const SizedBox(height: 14),

                        // Negeri
                        DropdownButtonFormField<int>(
                          initialValue: _selectedNegeri,
                          decoration: const InputDecoration(labelText: 'Negeri', prefixIcon: Icon(Icons.location_on_outlined)),
                          items: _negeriList.map((n) => DropdownMenuItem<int>(value: n['id'], child: Text(n['nama'] ?? ''))).toList(),
                          onChanged: _onNegeriChanged,
                          validator: (v) => v == null ? 'Sila pilih negeri' : null,
                        ),
                        const SizedBox(height: 14),

                        // Bandar
                        DropdownButtonFormField<int>(
                          initialValue: _selectedBandar,
                          decoration: const InputDecoration(labelText: 'Bandar / Parlimen', prefixIcon: Icon(Icons.location_city_outlined)),
                          items: _bandarList.map((b) => DropdownMenuItem<int>(value: b['id'], child: Text(b['nama'] ?? ''))).toList(),
                          onChanged: (v) => setState(() { _selectedBandar = v; _onBandarChanged(v); }),
                          validator: (v) => v == null ? 'Sila pilih bandar' : null,
                        ),
                        const SizedBox(height: 14),

                        // DUN
                        DropdownButtonFormField<int>(
                          initialValue: _selectedKadun,
                          decoration: const InputDecoration(labelText: 'DUN', prefixIcon: Icon(Icons.how_to_vote_outlined)),
                          items: _kadunList.map((k) => DropdownMenuItem<int>(value: k['id'], child: Text(k['nama'] ?? ''))).toList(),
                          onChanged: (v) => setState(() => _selectedKadun = v),
                          validator: (v) => v == null ? 'Sila pilih DUN' : null,
                        ),
                        const SizedBox(height: 20),

                        // Password
                        TextFormField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          decoration: InputDecoration(
                            labelText: 'Kata Laluan',
                            prefixIcon: const Icon(Icons.lock_outlined),
                            suffixIcon: IconButton(icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _obscurePassword = !_obscurePassword)),
                          ),
                          validator: (v) => v == null || v.length < 8 ? 'Kata laluan minimum 8 aksara' : null,
                        ),
                        const SizedBox(height: 14),

                        // Confirm Password
                        TextFormField(
                          controller: _confirmPasswordController,
                          obscureText: _obscureConfirm,
                          decoration: InputDecoration(
                            labelText: 'Sahkan Kata Laluan',
                            prefixIcon: const Icon(Icons.lock_outlined),
                            suffixIcon: IconButton(icon: Icon(_obscureConfirm ? Icons.visibility_off : Icons.visibility), onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm)),
                          ),
                          validator: (v) {
                            if (v == null || v.isEmpty) return 'Sila sahkan kata laluan';
                            if (v != _passwordController.text) return 'Kata laluan tidak sepadan';
                            return null;
                          },
                        ),
                        const SizedBox(height: 24),

                        // Submit
                        SizedBox(
                          height: 50,
                          child: ElevatedButton(
                            onPressed: _isLoading ? null : _register,
                            child: _isLoading
                              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Text('Daftar'),
                          ),
                        ),

                        const SizedBox(height: 16),
                        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                          const Text('Sudah mendaftar? ', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          GestureDetector(
                            onTap: () => Navigator.pop(context),
                            child: const Text('Log Masuk', style: TextStyle(color: AppTheme.accent, fontSize: 13, fontWeight: FontWeight.w700)),
                          ),
                        ]),
                      ]),
                    ),
                  ),
                ]),
              ),
            ),
          ]),
        ),
      ),
    );
  }

  @override
  void dispose() {
    _nameController.dispose();
    _telephoneController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }
}
