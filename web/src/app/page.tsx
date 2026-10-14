import Link from "next/link";
import { SEO_ROUTES, seoPath } from "@/lib/seo";

export default function Home() {
  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
      <h1 className="text-3xl font-semibold">ChezVous</h1>
      <p>Trouvez. Vérifiez. Visitez. Des biens dont la disponibilité est datée, avec leur Passeport.</p>
      <form action="/annonces" method="get" role="search" className="flex gap-2">
        <label className="flex-1">
          <span className="sr-only">Quartier, repère…</span>
          <input name="q" placeholder="Yoff, Almadies, VDN…" className="w-full rounded border border-zinc-400 px-3 py-2" />
        </label>
        <button type="submit" className="rounded bg-zinc-900 px-4 py-2 text-white">
          Rechercher
        </button>
      </form>
      <nav aria-label="Recherches fréquentes" className="flex flex-col gap-1 underline">
        <Link href={seoPath("rent_long", "appartements", "yoff")}>Appartements {SEO_ROUTES.rent_long.verb} à Yoff</Link>
        <Link href={seoPath("rent_long", "appartements", "almadies")}>Appartements {SEO_ROUTES.rent_long.verb} aux Almadies</Link>
        <Link href={seoPath("rent_long", "maisons-villas", "ouakam")}>Maisons et villas {SEO_ROUTES.rent_long.verb} à Ouakam</Link>
      </nav>
      <nav aria-label="Compte" className="flex flex-wrap gap-4 underline">
        <Link href="/inscription">Créer un compte</Link>
        <Link href="/connexion">Se connecter</Link>
        <Link href="/demandes">Mes demandes</Link>
        <Link href="/boite">Demandes reçues</Link>
        <Link href="/favoris">Favoris</Link>
        <Link href="/alertes">Alertes</Link>
        <Link href="/notifications">Notifications</Link>
      </nav>
    </main>
  );
}
