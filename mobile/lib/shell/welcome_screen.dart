import 'package:flutter/material.dart';

import '../ui/theme.dart';
import '../ui/widgets.dart';

/// Écran de bienvenue, affiché une seule fois : promesse du produit puis entrée dans l'application.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({
    super.key,
    required this.onStart,
    required this.onSignIn,
  });

  final VoidCallback onStart;
  final VoidCallback onSignIn;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.sand,
    body: SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Spacer(),
            const Center(child: BrandMark(height: 120)),
            const SizedBox(height: 28),
            const Center(child: BrandLogo(size: 34)),
            const SizedBox(height: 28),
            Text(
              'Trouvez votre chez-vous en toute confiance',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineSmall
                  ?.copyWith(height: 1.2),
            ),
            const SizedBox(height: 10),
            Text(
              'Des annonces dont la fraîcheur est datée et un Passeport du Bien qui liste les contrôles effectués.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const Spacer(),
            FilledButton(onPressed: onStart, child: const Text('Commencer')),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: onSignIn,
              child: const Text('Se connecter'),
            ),
          ],
        ),
      ),
    ),
  );
}
