import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../core/api_client.dart';
import 'theme.dart';

/// Composants volontairement sobres : le design final viendra de Figma.
class AppField extends StatelessWidget {
  const AppField({
    super.key,
    required this.controller,
    required this.label,
    this.error,
    this.obscure = false,
    this.keyboard,
    this.autofill,
  });

  final TextEditingController controller;
  final String label;
  final String? error;
  final bool obscure;
  final TextInputType? keyboard;
  final Iterable<String>? autofill;

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    obscureText: obscure,
    keyboardType: keyboard,
    autofillHints: autofill,
    decoration: InputDecoration(
      labelText: label,
      errorText: error,
      border: const OutlineInputBorder(),
    ),
  );
}

class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.pending = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool pending;

  @override
  Widget build(BuildContext context) => FilledButton(
    onPressed: pending ? null : onPressed,
    child: Text(pending ? 'Veuillez patienter…' : label),
  );
}

/// Message d'erreur lisible pour toute exception.
String errorText(Object e) =>
    e is ApiException ? e.message : 'Une erreur est survenue.';

/// Pictogramme de la marque (maison et repère) en vectoriel : net à toutes les tailles.
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.height = 32, this.icon = false});

  final double height;

  /// Version simplifiée (icône d'application) plutôt que le pictogramme complet.
  final bool icon;

  @override
  Widget build(BuildContext context) => SvgPicture.asset(
    icon ? 'assets/brand/iconmark.svg' : 'assets/brand/mark.svg',
    height: height,
    semanticsLabel: 'ChezVous',
  );
}

/// Logo : pictogramme + mot-symbole (« Chez » anthracite, « Vous » en orange de marque). Un logotype est exempté des exigences de contraste.
class BrandLogo extends StatelessWidget {
  const BrandLogo({super.key, this.size = 22, this.light = false});

  final double size;

  /// Fond sombre : « Chez » en blanc.
  final bool light;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      BrandMark(height: size * 1.4),
      SizedBox(width: size * 0.35),
      Text.rich(
        TextSpan(
          text: 'Chez',
          style: TextStyle(color: light ? Colors.white : AppColors.ink),
          children: const [
            TextSpan(
              text: 'Vous',
              style: TextStyle(color: AppColors.brandOrange),
            ),
          ],
        ),
        style: TextStyle(
          fontSize: size,
          fontWeight: FontWeight.w900,
          letterSpacing: -0.6,
          height: 1,
        ),
      ),
    ],
  );
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
