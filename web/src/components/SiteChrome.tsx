"use client";

import Image from "next/image";
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

export function Logo() {
  return (
    <Link href="/" className="flex min-h-11 items-center gap-2" aria-label="ChezVous, accueil">
      <Image src="/brand/mark.png" alt="" width={44} height={36} priority />
      <span className="text-xl font-extrabold tracking-tight">
        Chez<span className="text-brand-red">Vous</span>
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
              <Link href="/connexion" className="flex min-h-11 items-center rounded-lg px-3.5 text-sm font-bold text-primary hover:bg-primary-soft">
                Se connecter
              </Link>
              <Link href="/inscription" className="flex min-h-11 items-center rounded-lg bg-primary px-4 text-sm font-bold text-white hover:bg-primary-hover">
                Créer un compte
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
