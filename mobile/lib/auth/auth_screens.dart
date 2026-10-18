import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'auth_controller.dart';

const _phonePrefix = Padding(
  padding: EdgeInsets.only(right: 8),
  child: Text('🇸🇳 +221', style: TextStyle(fontWeight: FontWeight.w700)),
);

Widget _errorLine(BuildContext context, String? message) => message == null
    ? const SizedBox.shrink()
    : Padding(
        padding: const EdgeInsets.only(top: 12),
        child: Text(
          message,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
      );

/// Page à bandeau photo, puis feuille blanche arrondie : connexion et mot de passe oublié.
class _PhotoSheet extends StatelessWidget {
  const _PhotoSheet({
    required this.eyebrow,
    required this.lead,
    required this.accent,
    required this.subtitle,
    required this.children,
    this.onBack,
  });

  final String eyebrow;
  final String lead;
  final String accent;
  final String subtitle;
  final List<Widget> children;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.white,
    body: Column(
      children: [
        SizedBox(
          height: 300,
          width: double.infinity,
          child: Stack(
            fit: StackFit.expand,
            children: [
              Image.asset(
                'assets/images/villa-nuit.jpg',
                fit: BoxFit.cover,
                alignment: Alignment.topCenter,
                excludeFromSemantics: true,
              ),
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Color(0x99000000),
                      Color(0x33000000),
                      Color(0xCC000000),
                    ],
                  ),
                ),
              ),
              SafeArea(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 36),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          if (onBack != null)
                            IconButton(
                              tooltip: 'Retour',
                              onPressed: onBack,
                              icon: const Icon(
                                Icons.arrow_back,
                                color: Colors.white,
                              ),
                            ),
                          const BrandLogo(size: 15, light: true),
                        ],
                      ),
                      const Spacer(),
                      Eyebrow(eyebrow, light: true),
                      const SizedBox(height: 6),
                      AccentHeadline(lead, accent, size: 32, light: true),
                      const SizedBox(height: 6),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: Transform.translate(
            offset: const Offset(0, -24),
            child: Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
              ),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
                children: children,
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

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
  Widget build(BuildContext context) => _PhotoSheet(
    eyebrow: 'Votre espace personnel',
    lead: 'Bon retour ',
    accent: 'chez vous.',
    subtitle: 'Retrouvez vos favoris, vos messages et vos prochaines visites.',
    children: [
      Text(
        'Se connecter',
        style: Theme.of(context).textTheme.headlineSmall
            ?.copyWith(fontSize: 26),
      ),
      const SizedBox(height: 4),
      Text(
        'Votre expérience immobilière, en toute confiance.',
        style: Theme.of(context).textTheme.bodySmall,
      ),
      const SizedBox(height: 22),
      LabeledField(
        controller: _phone,
        label: 'Numéro de téléphone',
        hint: '77 123 45 67',
        icon: Icons.phone_outlined,
        prefix: _phonePrefix,
        keyboard: TextInputType.phone,
        autofill: const [AutofillHints.telephoneNumber],
      ),
      const SizedBox(height: 16),
      LabeledField(
        controller: _password,
        label: 'Mot de passe',
        hint: '••••••••',
        icon: Icons.lock_outline,
        obscure: true,
        autofill: const [AutofillHints.password],
      ),
      Align(
        alignment: Alignment.centerRight,
        child: TextButton(
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => ForgotPasswordScreen(auth: widget.auth),
            ),
          ),
          child: const Text('Mot de passe oublié ?'),
        ),
      ),
      _errorLine(context, _error),
      const SizedBox(height: 8),
      PrimaryButton(
        label: 'Se connecter',
        pending: _pending,
        arrow: true,
        onPressed: _submit,
      ),
      const SizedBox(height: 16),
      Center(
        child: Text.rich(
          TextSpan(
            text: 'Pas encore de compte ? ',
            style: const TextStyle(color: AppColors.muted),
            children: [
              WidgetSpan(
                alignment: PlaceholderAlignment.baseline,
                baseline: TextBaseline.alphabetic,
                child: GestureDetector(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => RegisterScreen(auth: widget.auth),
                    ),
                  ),
                  child: const Text(
                    'Créer un compte',
                    style: TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ],
  );
}

/// Mot de passe oublié : numéro, puis code reçu et nouveau mot de passe.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  final _password = TextEditingController();
  bool _codeSent = false;
  bool _pending = false;
  String? _error;

  @override
  void dispose() {
    _phone.dispose();
    _code.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _pending = true;
      _error = null;
    });
    try {
      await action();
    } catch (e) {
      _error = errorText(e);
    }
    if (mounted) setState(() => _pending = false);
  }

  Future<void> _send() => _run(() async {
    await widget.auth.sendResetCode(_phone.text.trim());
    _codeSent = true;
  });

  Future<void> _reset() => _run(() async {
    await widget.auth.resetPassword(
      _phone.text.trim(),
      _code.text.trim(),
      _password.text,
    );
    if (!mounted) return;
    Navigator.of(context).pop();
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Mot de passe modifié : vous pouvez vous connecter.'),
      ),
    );
  });

  @override
  Widget build(BuildContext context) => _PhotoSheet(
    eyebrow: 'Mot de passe oublié',
    lead: 'On vous aide ',
    accent: 'à rentrer.',
    subtitle: 'Recevez un code pour choisir un nouveau mot de passe.',
    onBack: () => Navigator.of(context).maybePop(),
    children: [
      LabeledField(
        controller: _phone,
        label: 'Numéro de téléphone',
        hint: '77 123 45 67',
        icon: Icons.phone_outlined,
        prefix: _phonePrefix,
        keyboard: TextInputType.phone,
      ),
      if (_codeSent) ...[
        const SizedBox(height: 16),
        LabeledField(
          controller: _code,
          label: 'Code reçu',
          hint: '6 chiffres',
          icon: Icons.pin_outlined,
          keyboard: TextInputType.number,
          autofill: const [AutofillHints.oneTimeCode],
        ),
        const SizedBox(height: 16),
        LabeledField(
          controller: _password,
          label: 'Nouveau mot de passe',
          hint: '8 caractères minimum',
          icon: Icons.lock_outline,
          obscure: true,
        ),
      ],
      _errorLine(context, _error),
      const SizedBox(height: 20),
      PrimaryButton(
        label: _codeSent ? 'Changer le mot de passe' : 'Recevoir un code',
        pending: _pending,
        arrow: true,
        onPressed: _codeSent ? _reset : _send,
      ),
    ],
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
  bool _terms = false;
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
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Row(
            children: [
              IconButton.outlined(
                tooltip: 'Retour',
                onPressed: () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.arrow_back),
              ),
              const SizedBox(width: 12),
              const Text(
                'Créer un compte',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
              ),
            ],
          ),
          const SizedBox(height: 28),
          const Eyebrow('Étape 1 sur 2 · Votre profil'),
          const SizedBox(height: 8),
          const AccentHeadline('Bienvenue dans ', 'ChezVous.'),
          const SizedBox(height: 8),
          Text(
            'Créez votre espace en quelques instants.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 22),
          SegmentedButton<bool>(
            showSelectedIcon: false,
            segments: const [
              ButtonSegment(value: false, label: Text('Particulier')),
              ButtonSegment(value: true, label: Text('Professionnel')),
            ],
            selected: {_agency},
            onSelectionChanged: (v) => setState(() => _agency = v.first),
          ),
          const SizedBox(height: 20),
          LabeledField(
            controller: _name,
            label: 'Nom et prénom',
            hint: 'Votre nom complet',
            icon: Icons.person_outline,
            error: _f('name'),
          ),
          if (_agency) ...[
            const SizedBox(height: 16),
            LabeledField(
              controller: _agencyName,
              label: "Nom de l'agence",
              icon: Icons.business_outlined,
              error: _f('agency_name'),
            ),
          ],
          const SizedBox(height: 16),
          LabeledField(
            controller: _phone,
            label: 'Téléphone',
            hint: '77 000 00 00',
            prefix: _phonePrefix,
            keyboard: TextInputType.phone,
            error: _f('phone'),
          ),
          const SizedBox(height: 16),
          LabeledField(
            controller: _email,
            label: 'Adresse e-mail',
            hint: 'nom@exemple.com',
            icon: Icons.mail_outline,
            keyboard: TextInputType.emailAddress,
            error: _f('email'),
          ),
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              'Le code de vérification y est envoyé.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
          const SizedBox(height: 16),
          LabeledField(
            controller: _password,
            label: 'Créer un mot de passe',
            hint: '8 caractères minimum',
            icon: Icons.lock_outline,
            obscure: true,
            error: _f('password'),
          ),
          const SizedBox(height: 8),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            value: _terms,
            onChanged: (v) => setState(() => _terms = v ?? false),
            title: const Text(
              "J'accepte les conditions d'utilisation et la politique de confidentialité.",
              style: TextStyle(fontSize: 13),
            ),
          ),
          _errorLine(context, _error),
          const SizedBox(height: 12),
          PrimaryButton(
            label: 'Continuer',
            pending: _pending,
            arrow: true,
            onPressed: _terms ? _submit : null,
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.sand,
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            child: const Row(
              children: [
                Icon(Icons.verified_user_outlined, color: AppColors.primary),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    "Vérification du compte par code à l'étape suivante.",
                    style: TextStyle(fontSize: 13),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
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
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: IconButton.outlined(
              tooltip: 'Retour',
              onPressed: () => Navigator.of(context).maybePop(),
              icon: const Icon(Icons.arrow_back),
            ),
          ),
          const SizedBox(height: 20),
          const Eyebrow('Étape 2 sur 2 · Vérification'),
          const SizedBox(height: 8),
          const AccentHeadline('Vérifions ', 'votre compte.'),
          const SizedBox(height: 10),
          Text(
            'Saisissez le code reçu par e-mail pour le compte ${widget.phone}. Pensez à vérifier vos courriers indésirables.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 22),
          LabeledField(
            controller: _code,
            label: 'Code à 6 chiffres',
            hint: '000000',
            icon: Icons.pin_outlined,
            keyboard: TextInputType.number,
            autofill: const [AutofillHints.oneTimeCode],
          ),
          _errorLine(context, _error),
          if (_info != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(_info!),
            ),
          const SizedBox(height: 24),
          PrimaryButton(
            label: 'Valider',
            pending: _pending,
            arrow: true,
            onPressed: _verify,
          ),
          Center(
            child: TextButton(
              onPressed: _resend,
              child: const Text('Renvoyer le code'),
            ),
          ),
        ],
      ),
    ),
  );
}
