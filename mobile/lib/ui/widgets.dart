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
