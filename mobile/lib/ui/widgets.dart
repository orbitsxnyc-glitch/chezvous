import 'package:flutter/material.dart';

import '../core/api_client.dart';

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

/// Logo de marque (image fournie) pour les écrans d'accueil du compte.
class BrandHeader extends StatelessWidget {
  const BrandHeader({super.key});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: Center(
      child: Image.asset(
        'assets/brand/logo.png',
        height: 120,
        semanticLabel: 'ChezVous',
      ),
    ),
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
