import 'package:flutter/material.dart';

import '../ui/theme.dart';
import '../ui/widgets.dart';

/// Écran de bienvenue (affiché une seule fois) : photo plein écran, promesse, quatre atouts, « Commencer ».
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({
    super.key,
    required this.onStart,
    required this.onSignIn,
  });

  final VoidCallback onStart;
  final VoidCallback onSignIn;

  static const _perks = [
    (Icons.event_available_outlined, 'Disponibilité\ndatée'),
    (Icons.verified_user_outlined, 'Passeport\ndu Bien'),
    (Icons.location_on_outlined, 'Spécial\nDakar'),
    (Icons.chat_bubble_outline, 'Contact\nsécurisé'),
  ];

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFF0E1726),
    body: Stack(
      fit: StackFit.expand,
      children: [
        Image.asset(
          'assets/images/dakar-crepuscule.jpg',
          fit: BoxFit.cover,
          alignment: const Alignment(0.55, 0),
          excludeFromSemantics: true,
        ),
        // Dégradé : le texte reste lisible quelle que soit la photo.
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0x66000000), Color(0x00000000), Color(0xE6000000)],
              stops: [0, 0.35, 0.8],
            ),
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Center(
                  child: BrandLogo(size: 26, light: true, tagline: true),
                ),
                const Spacer(),
                const AccentHeadline(
                  'Trouvez votre chez-vous ',
                  'en toute confiance',
                  size: 36,
                  light: true,
                ),
                const SizedBox(height: 12),
                const Text(
                  'Des logements dont la disponibilité est datée, à Dakar.',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 20),
                Row(
                  children: [
                    for (final (icon, label) in _perks)
                      Expanded(
                        child: Container(
                          margin: const EdgeInsets.only(right: 8),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(AppRadius.md),
                            border: Border.all(color: Colors.white24),
                          ),
                          child: Column(
                            children: [
                              Icon(icon, color: Colors.white, size: 24),
                              const SizedBox(height: 6),
                              Text(
                                label,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  height: 1.25,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 22),
                Material(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(40),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(40),
                    onTap: onStart,
                    child: Container(
                      height: 60,
                      padding: const EdgeInsets.only(left: 28, right: 8),
                      child: Row(
                        children: [
                          const Expanded(
                            child: Center(
                              child: Text(
                                'Commencer',
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 17,
                                ),
                              ),
                            ),
                          ),
                          Container(
                            width: 46,
                            height: 46,
                            decoration: const BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: LinearGradient(
                                colors: [
                                  AppColors.primary,
                                  AppColors.primaryHover,
                                ],
                              ),
                            ),
                            child: const Icon(
                              Icons.arrow_forward,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Center(
                  child: TextButton(
                    onPressed: onSignIn,
                    style: TextButton.styleFrom(foregroundColor: Colors.white),
                    child: const Text.rich(
                      TextSpan(
                        text: 'Déjà un compte ? ',
                        children: [
                          TextSpan(
                            text: 'Se connecter',
                            style: TextStyle(
                              color: AppColors.brandOrange,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}
