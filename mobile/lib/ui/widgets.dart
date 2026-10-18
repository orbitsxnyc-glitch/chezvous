import 'package:flutter/material.dart';

import '../core/api_client.dart';
import 'theme.dart';

/// Composants volontairement sobres : le design final viendra de Figma.
class AppField extends StatefulWidget {
  const AppField({
    super.key,
    required this.controller,
    required this.label,
    this.error,
    this.obscure = false,
    this.keyboard,
    this.autofill,
    this.prefix,
    this.hint,
  });

  final TextEditingController controller;
  final String label;
  final String? error;
  final bool obscure;
  final TextInputType? keyboard;
  final Iterable<String>? autofill;
  final Widget? prefix;
  final String? hint;

  @override
  State<AppField> createState() => _AppFieldState();
}

class _AppFieldState extends State<AppField> {
  late bool _hidden = widget.obscure;

  @override
  Widget build(BuildContext context) => TextField(
    controller: widget.controller,
    obscureText: _hidden,
    keyboardType: widget.keyboard,
    autofillHints: widget.autofill,
    decoration: InputDecoration(
      labelText: widget.label,
      hintText: widget.hint,
      errorText: widget.error,
      prefix: widget.prefix,
      border: const OutlineInputBorder(),
      // Œil : afficher ou masquer le mot de passe saisi.
      suffixIcon: widget.obscure
          ? IconButton(
              tooltip: _hidden
                  ? 'Afficher le mot de passe'
                  : 'Masquer le mot de passe',
              icon: Icon(
                _hidden
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
              ),
              onPressed: () => setState(() => _hidden = !_hidden),
            )
          : null,
    ),
  );
}

/// Bouton principal du design : dégradé orange, coins arrondis, flèche facultative.
/// Le texte est blanc sur la partie la plus foncée du dégradé (#C93F00 → #B03500) pour rester lisible.
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.pending = false,
    this.arrow = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool pending;
  final bool arrow;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !pending;
    return Opacity(
      opacity: enabled ? 1 : 0.5,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [AppColors.primary, AppColors.primaryHover],
          ),
          borderRadius: BorderRadius.circular(AppRadius.md),
          boxShadow: enabled
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.25),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ]
              : null,
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            borderRadius: BorderRadius.circular(AppRadius.md),
            onTap: enabled ? onPressed : null,
            child: Container(
              constraints: const BoxConstraints(minHeight: 56),
              alignment: Alignment.center,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Flexible(
                    child: Text(
                      pending ? 'Veuillez patienter…' : label,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  if (arrow && !pending) ...[
                    const SizedBox(width: 8),
                    const Icon(
                      Icons.arrow_forward,
                      color: Colors.white,
                      size: 20,
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Titre du design : texte anthracite puis fin de phrase en orange (« Bon retour *chez vous.* »).
class AccentHeadline extends StatelessWidget {
  const AccentHeadline(
    this.lead,
    this.accent, {
    super.key,
    this.size = 30,
    this.light = false,
  });

  final String lead;
  final String accent;
  final double size;
  final bool light;

  @override
  Widget build(BuildContext context) => Text.rich(
    TextSpan(
      text: lead,
      style: TextStyle(color: light ? Colors.white : AppColors.ink),
      children: [
        TextSpan(
          text: accent,
          style: TextStyle(
            color: light ? AppColors.brandOrange : AppColors.primary,
          ),
        ),
      ],
    ),
    style: TextStyle(
      fontSize: size,
      fontWeight: FontWeight.w900,
      letterSpacing: -1,
      height: 1.08,
    ),
  );
}

/// Petite surtitre en capitales espacées (« VOTRE ESPACE PERSONNEL »).
class Eyebrow extends StatelessWidget {
  const Eyebrow(this.text, {super.key, this.light = false});

  final String text;
  final bool light;

  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    style: TextStyle(
      fontSize: 12,
      fontWeight: FontWeight.w800,
      letterSpacing: 2,
      color: light ? AppColors.brandOrange : AppColors.primary,
    ),
  );
}

/// Champ du design : intitulé en gras au-dessus, icône à gauche, œil pour les mots de passe.
class LabeledField extends StatefulWidget {
  const LabeledField({
    super.key,
    required this.controller,
    required this.label,
    this.hint,
    this.icon,
    this.prefix,
    this.error,
    this.obscure = false,
    this.keyboard,
    this.autofill,
    this.optional = false,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final IconData? icon;
  final Widget? prefix;
  final String? error;
  final bool obscure;
  final TextInputType? keyboard;
  final Iterable<String>? autofill;
  final bool optional;

  @override
  State<LabeledField> createState() => _LabeledFieldState();
}

class _LabeledFieldState extends State<LabeledField> {
  late bool _hidden = widget.obscure;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text.rich(
        TextSpan(
          text: widget.label,
          children: [
            if (widget.optional)
              const TextSpan(
                text: ' (facultatif)',
                style: TextStyle(
                  color: AppColors.muted,
                  fontWeight: FontWeight.w500,
                ),
              ),
          ],
        ),
        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
      ),
      const SizedBox(height: 8),
      TextField(
        controller: widget.controller,
        obscureText: _hidden,
        keyboardType: widget.keyboard,
        autofillHints: widget.autofill,
        decoration: InputDecoration(
          hintText: widget.hint,
          errorText: widget.error,
          prefixIcon: widget.prefix != null
              ? Padding(
                  padding: const EdgeInsets.only(left: 16, right: 10),
                  child: widget.prefix,
                )
              : widget.icon == null
              ? null
              : Icon(widget.icon, color: AppColors.muted),
          prefixIconConstraints: widget.prefix != null
              ? const BoxConstraints(minWidth: 0, minHeight: 0)
              : null,
          suffixIcon: widget.obscure
              ? IconButton(
                  tooltip: _hidden
                      ? 'Afficher le mot de passe'
                      : 'Masquer le mot de passe',
                  icon: Icon(
                    _hidden
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                  ),
                  onPressed: () => setState(() => _hidden = !_hidden),
                )
              : null,
        ),
      ),
    ],
  );
}

/// Message d'erreur lisible pour toute exception.
String errorText(Object e) =>
    e is ApiException ? e.message : 'Une erreur est survenue.';

/// Pictogramme de la marque (fichier fourni par le porteur du projet, fond transparent).
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.height = 32, this.icon = false});

  final double height;

  /// Conservé pour compatibilité : le pictogramme est le même partout.
  final bool icon;

  @override
  Widget build(BuildContext context) => Image.asset(
    'assets/brand/mark.png',
    height: height,
    semanticLabel: 'ChezVous',
    filterQuality: FilterQuality.medium,
  );
}

/// Logo (pictogramme + « ChezVous »). `light` : variante pour fonds sombres. `tagline` : avec la signature.
class BrandLogo extends StatelessWidget {
  const BrandLogo({
    super.key,
    this.size = 22,
    this.light = false,
    this.tagline = false,
  });

  /// Hauteur du texte de référence ; l'image est dimensionnée en conséquence.
  final double size;
  final bool light;
  final bool tagline;

  @override
  Widget build(BuildContext context) {
    final name = tagline ? 'logo-full' : 'logo-wordmark';
    return Image.asset(
      'assets/brand/$name${light ? '-light' : ''}.png',
      height: size * (tagline ? 2.6 : 2.2),
      semanticLabel: 'ChezVous',
      filterQuality: FilterQuality.medium,
    );
  }
}

/// Logo centré pour les écrans d'accueil du compte.
class BrandHeader extends StatelessWidget {
  const BrandHeader({super.key});

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.only(bottom: 24, top: 8),
    child: Center(child: BrandLogo(size: 30)),
  );
}

/// État d'erreur réutilisable : message clair et bouton « Réessayer » (réseau coupé, lenteur, panne passagère).
class ErrorRetry extends StatelessWidget {
  const ErrorRetry({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_outlined,
            size: 40,
            color: Color(0xFF6B5E55),
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('Réessayer'),
          ),
        ],
      ),
    ),
  );
}
