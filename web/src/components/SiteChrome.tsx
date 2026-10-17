"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "./AuthProvider";

const PUBLIC_LINKS = [
  ["/annonces", "Rechercher"],
  ["/offres", "Offres"],
] as const;

const MEMBER_LINKS = [
  ["/annonces", "Rechercher"],
  ["/mes-annonces", "Mes annonces"],
  ["/demandes", "Messages"],
  ["/favoris", "Favoris"],
] as const;

/** Pictogramme de la marque (maison et repère), en SVG : net à toutes les tailles, sans fichier à charger. */
export function BrandMark({ className = "h-9 w-auto" }: { className?: string }) {
  return (
    <svg viewBox="0 0 750 720" className={className} aria-hidden>
      <defs>
        <linearGradient id="bm-roof" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stopColor="#F2530D" /><stop offset="1" stopColor="#FF7A10" /></linearGradient>
        <linearGradient id="bm-slab" x1="0.5" y1="0" x2="0.5" y2="1"><stop offset="0" stopColor="#FF9A1F" /><stop offset="1" stopColor="#F2530D" /></linearGradient>
        <linearGradient id="bm-ring" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#FF6A00" /><stop offset="0.6" stopColor="#FF7A10" /><stop offset="1" stopColor="#FFB020" /></linearGradient>
        <linearGradient id="bm-fold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#F25A06" /><stop offset="1" stopColor="#D84200" /></linearGradient>
      </defs>
      <path fill="url(#bm-roof)" d="M400 55 Q412 55 424 64 L690 275 Q702 285 702 300 L702 392 Q702 402 692 396 L400 175 L112 393 Q70 412 38 380 Q10 345 40 318 L376 62 Q388 55 400 55 Z" />
      <path fill="url(#bm-slab)" d="M232 352 L232 580 Q232 600 214 611 L150 653 Q115 672 115 632 L115 442 Q115 428 126 420 Z" />
      <path fill="url(#bm-ring)" fillRule="evenodd" d="M405 272 C480 272 548 330 548 410 C548 438 540 462 540 480 L545 600 Q545 640 510 652 Q484 660 462 642 L360 548 C310 520 258 470 258 410 C258 330 330 272 405 272 Z M405 340 C372 340 336 366 336 408 C336 438 352 460 372 492 L402 540 L432 492 C452 460 474 438 474 408 C474 366 438 340 405 340 Z" />
      <path fill="url(#bm-fold)" d="M545 600 Q545 640 510 652 Q484 660 462 642 L360 548 C420 560 500 500 532 430 C538 450 542 470 542 490 Z" />
      <circle cx="404" cy="415" r="24" fill="#FF6A00" />
    </svg>
  );
}

/** Logo : pictogramme + mot-symbole (« Chez » anthracite, « Vous » en dégradé orange). Un logotype est exempté des exigences de contraste. */
export function Logo() {
  return (
    <Link href="/" className="flex min-h-11 items-center gap-2" aria-label="ChezVous, accueil">
      <BrandMark />
      <span className="text-[1.45rem] leading-none font-black tracking-tight" aria-hidden>
        Chez<span className="bg-[image:var(--brand-gradient)] bg-clip-text text-transparent">Vous</span>
      </span>
    </Link>
  );
}

/** En-tête commun. Les pages d'administration ont leur propre barre : on ne la double pas. */
export function SiteHeader() {
  const pathname = usePathname();
  const { user, loading } = useAuth();
  if (pathname.startsWith("/admin")) return null;
  const links = user ? MEMBER_LINKS : PUBLIC_LINKS;

  return (
    <header className="sticky top-0 z-50 border-b border-line bg-white/95 backdrop-blur">
      <div className="mx-auto flex min-h-[68px] w-full max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-2">
        <Logo />
        <nav aria-label="Navigation principale" className="order-3 flex w-full gap-1 overflow-x-auto md:order-none md:w-auto md:flex-1">
          {links.map(([href, label]) => {
            const active = pathname === href || pathname.startsWith(`${href}/`);
            return (
              <Link
                key={href}
                href={href}
                aria-current={active ? "page" : undefined}
                className={`flex min-h-11 shrink-0 items-center rounded-lg px-3.5 text-sm font-semibold transition-colors hover:bg-primary-soft hover:text-primary ${active ? "bg-primary-soft text-primary" : "text-muted"}`}
              >
                {label}
              </Link>
            );
          })}
        </nav>
        <div className="flex items-center gap-2">
          {loading ? null : user ? (
            <Link href="/profil" className="flex size-10 items-center justify-center rounded-full bg-sand-deep font-extrabold text-primary" aria-label="Mon compte">
              {user.name.trim().charAt(0).toUpperCase()}
            </Link>
          ) : (
            <>
              <Link href="/connexion" className="flex min-h-11 items-center rounded-lg px-2.5 text-sm font-bold text-primary hover:bg-primary-soft sm:px-3.5">
                Se connecter
              </Link>
              <Link href="/inscription" className="flex min-h-11 items-center rounded-lg bg-primary px-4 text-sm font-bold text-white hover:bg-primary-hover">
                <span className="sm:hidden">S&apos;inscrire</span>
                <span className="hidden sm:inline">Créer un compte</span>
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}

export function SiteFooter() {
  const pathname = usePathname();
  if (pathname.startsWith("/admin")) return null;
  return (
    <footer className="mt-16 border-t border-line bg-white">
      <div className="mx-auto flex w-full max-w-6xl flex-col gap-3 px-5 py-8 text-sm text-muted">
        <p className="font-semibold text-ink">ChezVous · location résidentielle à Dakar</p>
        <p>Un contrôle de document ne garantit pas la propriété juridique du bien. Les montants affichés sont renseignés par les annonceurs et doivent être confirmés avant tout paiement.</p>
      </div>
    </footer>
  );
}
