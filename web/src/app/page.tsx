import Link from "next/link";
import { Suspense } from "react";
import { cacheLife } from "next/cache";
import { connection } from "next/server";
import { ListingCard } from "@/components/listings/ListingCard";
import { getNeighborhoods, type ListingSummary } from "@/lib/listings";
import { publicGet } from "@/lib/server-api";
import { SEO_ROUTES, seoPath } from "@/lib/seo";

async function getFeatured(): Promise<ListingSummary[]> {
  "use cache";
  cacheLife("minutes");
  try {
    const res = await publicGet<{ data: ListingSummary[] }>("/featured");
    return res?.data ?? [];
  } catch {
    return []; // une panne de l'API ne doit pas casser l'accueil
  }
}

const BUDGETS = [100000, 200000, 300000, 500000, 1000000];
const nf = new Intl.NumberFormat("fr-FR");
const label = "text-xs font-bold text-[#39443f]";
const control = "min-h-12 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink";

/** Recherche rapide : les critères passent en paramètres d'URL vers /annonces (rendu serveur, sans JavaScript). */
async function SearchPanel() {
  await connection();
  const neighborhoods = await getNeighborhoods().catch(() => []);
  return (
    <form action="/annonces" method="get" role="search" className="w-full rounded-2xl border border-primary/10 bg-white p-4 text-left shadow-panel sm:p-5">
      <fieldset className="mb-4 flex w-fit rounded-xl bg-sand p-1">
        <legend className="sr-only">Type de transaction</legend>
        {[
          ["rent_long", "Location"],
          ["sale", "Vente"],
        ].map(([value, text]) => (
          <label key={value} className="cursor-pointer">
            <input type="radio" name="transaction" value={value} defaultChecked={value === "rent_long"} className="peer sr-only" />
            <span className="flex min-h-10 items-center rounded-lg px-5 text-sm font-bold text-muted peer-checked:bg-white peer-checked:text-primary peer-checked:shadow-card peer-focus-visible:outline-3 peer-focus-visible:outline-info/30">
              {text}
            </span>
          </label>
        ))}
      </fieldset>
      <div className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-[1.2fr_1.2fr_1fr_auto]">
        <label className="flex flex-col gap-1.5">
          <span className={label}>Quartier ou repère</span>
          <input name="q" placeholder="Yoff, Almadies, VDN…" className={control} />
        </label>
        <label className="flex flex-col gap-1.5">
          <span className={label}>Quartier</span>
          <select name="locality" className={control} defaultValue="">
            <option value="">Tous les quartiers</option>
            {neighborhoods.map((n) => (
              <option key={n.slug} value={n.slug}>{n.name}</option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1.5">
          <span className={label}>Budget maximum</span>
          <select name="price_max" className={control} defaultValue="">
            <option value="">Tous les budgets</option>
            {BUDGETS.map((b) => (
              <option key={b} value={b}>≤ {nf.format(b).replace(/ | /g, " ")} FCFA</option>
            ))}
          </select>
        </label>
        <button type="submit" className="min-h-12 rounded-lg bg-primary px-6 text-sm font-bold text-white shadow-card hover:bg-primary-hover">
          Rechercher
        </button>
      </div>
    </form>
  );
}

/** « À la une » : annonces mises en avant (plafonnées, en rotation). Signalées comme telles : une mise en avant n'est pas un label de confiance. */
async function Featured() {
  await connection();
  const items = await getFeatured();
  if (items.length === 0) return null;
  return (
    <section aria-labelledby="une" className="mx-auto w-full max-w-6xl px-5 pt-16">
      <p className="text-xs font-extrabold tracking-[0.13em] text-primary uppercase">Sélection</p>
      <h2 id="une" className="mt-2 text-3xl font-extrabold">À la une</h2>
      <p className="mt-2 text-sm text-muted">Annonces mises en avant par leurs annonceurs.</p>
      <ul className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((l) => (
          <li key={l.id}>
            <ListingCard listing={l} sponsored />
          </li>
        ))}
      </ul>
    </section>
  );
}

async function Districts() {
  await connection();
  const neighborhoods = await getNeighborhoods().catch(() => []);
  if (neighborhoods.length === 0) return null;
  return (
    <section aria-labelledby="quartiers" className="mx-auto w-full max-w-6xl px-5 pt-16">
      <h2 id="quartiers" className="text-3xl font-extrabold">Quartiers de Dakar</h2>
      <ul className="mt-6 flex flex-wrap gap-3">
        {neighborhoods.map((n) => (
          <li key={n.slug}>
            <Link href={`/annonces?transaction=rent_long&locality=${n.slug}`} className="flex min-h-11 items-center rounded-lg border border-line bg-white px-4 text-sm font-semibold hover:border-primary hover:text-primary">
              {n.name}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}

const TRUST = [
  ["Une disponibilité datée", "Chaque annonce indique quand son annonceur l'a confirmée pour la dernière fois. Plus l'information vieillit, moins l'annonce est mise en avant."],
  ["Le Passeport du Bien", "Des contrôles explicites et datés (téléphone, identité, localisation, document…), jamais une note globale. Un contrôle ne garantit pas la propriété juridique du bien."],
  ["Contact par la plateforme", "Les coordonnées de l'annonceur restent privées : vous le contactez par message, puis vous convenez d'une visite."],
] as const;

export default function Home() {
  return (
    <main>
      <section className="relative overflow-hidden border-b border-sand-deep bg-sand">
        <div className="mx-auto flex w-full max-w-4xl flex-col items-center px-5 pt-14 pb-16 text-center sm:pt-[70px]">
          <p className="text-xs font-extrabold tracking-[0.13em] text-primary uppercase">Location résidentielle à Dakar</p>
          <h1 className="mt-4 max-w-3xl text-4xl leading-[1.05] font-extrabold sm:text-6xl">
            Trouvez un logement <span className="text-primary">réellement disponible</span>
          </h1>
          <p className="mt-5 max-w-xl text-base leading-7 text-muted sm:text-lg">
            Trouvez. Vérifiez. Visitez. Des biens dont la disponibilité est datée, avec leur Passeport.
          </p>
          <div className="mt-9 w-full">
            <Suspense fallback={<div className="h-44 w-full rounded-2xl bg-white shadow-panel" aria-hidden />}>
              <SearchPanel />
            </Suspense>
          </div>
        </div>
      </section>

      <Suspense fallback={null}>
        <Featured />
      </Suspense>
      <Suspense fallback={null}>
        <Districts />
      </Suspense>

      <section aria-labelledby="confiance" className="mt-20 bg-primary py-16 text-white">
        <div className="mx-auto w-full max-w-6xl px-5">
          <p className="text-xs font-extrabold tracking-[0.13em] text-[#a9d4c5] uppercase">Pourquoi ChezVous</p>
          <h2 id="confiance" className="mt-2 max-w-2xl text-3xl font-extrabold">Mieux vaut peu d&apos;annonces, mais fiables.</h2>
          <ul className="mt-8 grid gap-5 md:grid-cols-3">
            {TRUST.map(([title, body], i) => (
              <li key={title} className={`rounded-xl border p-6 ${i === 1 ? "border-transparent bg-[#fdfbf7] text-ink" : "border-white/15 bg-white/[0.075]"}`}>
                <h3 className="text-lg font-extrabold">{title}</h3>
                <p className={`mt-2 text-sm leading-6 ${i === 1 ? "text-muted" : "text-white/80"}`}>{body}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <nav aria-label="Recherches fréquentes" className="mx-auto mt-12 flex w-full max-w-6xl flex-wrap gap-x-6 gap-y-2 px-5 text-sm font-semibold text-primary">
        <Link className="underline underline-offset-4" href={seoPath("rent_long", "appartements", "yoff")}>Appartements {SEO_ROUTES.rent_long.verb} à Yoff</Link>
        <Link className="underline underline-offset-4" href={seoPath("rent_long", "appartements", "almadies")}>Appartements {SEO_ROUTES.rent_long.verb} aux Almadies</Link>
        <Link className="underline underline-offset-4" href={seoPath("rent_long", "maisons-villas", "ouakam")}>Maisons et villas {SEO_ROUTES.rent_long.verb} à Ouakam</Link>
      </nav>
    </main>
  );
}
