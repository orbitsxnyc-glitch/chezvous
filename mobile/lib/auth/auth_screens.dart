import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../ui/widgets.dart';
import 'auth_controller.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _phone = TextEditingController();
  final _password = TextEditingController();
  String? _error;
  bool _pending = false;

  @override
  void dispose() {
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _pending = true;
      _error = null;
    });
    try {
      await widget.auth.login(_phone.text.trim(), _password.text);
    } on ApiException catch (e) {
      if (e.code == 'phone_not_verified' && mounted) {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) =>
                VerifyPhoneScreen(auth: widget.auth, phone: _phone.text.trim()),
          ),
        );
      }
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = errorText(e));
    }
    if (mounted) setState(() => _pending = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Connexion')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const BrandHeader(),
        AppField(
          controller: _phone,
          label: 'Téléphone (+221 7X XXX XX XX)',
          keyboard: TextInputType.phone,
          autofill: const [AutofillHints.telephoneNumber],
        ),
        const SizedBox(height: 16),
        AppField(
          controller: _password,
          label: 'Mot de passe',
          obscure: true,
          autofill: const [AutofillHints.password],
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        const SizedBox(height: 24),
        PrimaryButton(
          label: 'Se connecter',
          pending: _pending,
          onPressed: _submit,
        ),
        TextButton(
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => RegisterScreen(auth: widget.auth),
            ),
          ),
          child: const Text('Créer un compte'),
        ),
      ],
    ),
  );
}

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _agencyName = TextEditingController();
  bool _agency = false;
  bool _pending = false;
  String? _error;
  Map<String, List<String>> _fields = const {};

  @override
  void dispose() {
    for (final c in [_name, _phone, _email, _password, _agencyName]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _pending = true;
      _error = null;
      _fields = const {};
    });
    try {
      await widget.auth.register(
        name: _name.text.trim(),
        phone: _phone.text.trim(),
        password: _password.text,
        agency: _agency,
        email: _email.text.trim(),
        agencyName: _agencyName.text.trim(),
      );
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) =>
              VerifyPhoneScreen(auth: widget.auth, phone: _phone.text.trim()),
        ),
      );
      return;
    } on ApiException catch (e) {
      _error = e.errors.isEmpty ? e.message : null;
      _fields = e.errors;
    } catch (e) {
      _error = errorText(e);
    }
    if (mounted) setState(() => _pending = false);
  }

  String? _f(String k) => _fields[k]?.firstOrNull;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Créer un compte')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        AppField(controller: _name, label: 'Nom complet', error: _f('name')),
        const SizedBox(height: 16),
        AppField(
          controller: _phone,
          label: 'Téléphone (+221 7X XXX XX XX)',
          keyboard: TextInputType.phone,
          error: _f('phone'),
        ),
        const SizedBox(height: 16),
        AppField(
          controller: _email,
          label: 'E-mail (facultatif)',
          keyboard: TextInputType.emailAddress,
          error: _f('email'),
        ),
        const SizedBox(height: 16),
        AppField(
          controller: _password,
          label: 'Mot de passe (8 caractères minimum)',
          obscure: true,
          error: _f('password'),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Je suis une agence'),
          value: _agency,
          onChanged: (v) => setState(() => _agency = v),
        ),
        if (_agency)
          AppField(
            controller: _agencyName,
            label: "Nom de l'agence",
            error: _f('agency_name'),
          ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        const SizedBox(height: 24),
        PrimaryButton(
          label: 'Créer mon compte',
          pending: _pending,
          onPressed: _submit,
        ),
      ],
    ),
  );
}

class VerifyPhoneScreen extends StatefulWidget {
  const VerifyPhoneScreen({super.key, required this.auth, required this.phone});

  final AuthController auth;
  final String phone;

  @override
  State<VerifyPhoneScreen> createState() => _VerifyPhoneScreenState();
}

class _VerifyPhoneScreenState extends State<VerifyPhoneScreen> {
  final _code = TextEditingController();
  bool _pending = false;
  String? _error;
  String? _info;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    setState(() {
      _pending = true;
      _error = null;
    });
    try {
      await widget.auth.verifyPhone(widget.phone, _code.text.trim());
      if (!mounted) return;
      Navigator.of(context).popUntil((r) => r.isFirst);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Numéro vérifié : vous pouvez vous connecter.'),
        ),
      );
      return;
    } catch (e) {
      _error = errorText(e);
    }
    if (mounted) setState(() => _pending = false);
  }

  Future<void> _resend() async {
    try {
      await widget.auth.resendCode(widget.phone);
      if (mounted) {
        setState(() => _info = 'Un nouveau code vient d\'être envoyé.');
      }
    } catch (e) {
      if (mounted) setState(() => _error = errorText(e));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Vérifier mon numéro')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Text('Saisissez le code reçu pour ${widget.phone}.'),
        const SizedBox(height: 16),
        AppField(
          controller: _code,
          label: 'Code à 6 chiffres',
          keyboard: TextInputType.number,
          autofill: const [AutofillHints.oneTimeCode],
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        if (_info != null)
          Padding(padding: const EdgeInsets.only(top: 12), child: Text(_info!)),
        const SizedBox(height: 24),
        PrimaryButton(label: 'Valider', pending: _pending, onPressed: _verify),
        TextButton(onPressed: _resend, child: const Text('Renvoyer le code')),
      ],
    ),
  );
}
