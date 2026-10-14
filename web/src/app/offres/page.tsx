import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { cacheLife } from "next/cache";
import { connection } from "next/server";
import { publicGet } from "@/lib/server-api";
import { KIND_LABELS, PERIOD_LABELS, limitLabel, type Plan, type Product } from "@/lib/commercial";

export const metadata: Metadata = {
  title: "Offres et mises en avant — ChezVous",
  description: "Formules pour particuliers et agences, et options de mise en avant. Règlement hors ligne, activation après confirmation.",
  alternates: { canonical: "/offres" },
};

async function getCatalog(): Promise<{ plans: Plan[]; products: Product[] }> {
  "use cache";
  cacheLife("minutes");
  const [plans, products] = await Promise.all([publicGet<{ data: Plan[] }>("/plans"), publicGet<{ data: Product[] }>("/premium-products")]);
  return { plans: plans?.data ?? [], products: products?.data ?? [] };
}

const fcfa = (n: number) => `${new Intl.NumberFormat("fr-FR").format(n).replace(/ | /g, " ")} FCFA`;

async function Catalog() {
  await connection(); // lu à la demande : le build (et la CI) ne dépendent pas de l'API
  const { plans, products } = await getCatalog();
  const paid = plans.filter((p) => !p.free);
  return (
    <>
      <section aria-labelledby="formules" className="flex flex-col gap-3">
        <h2 id="formules" className="text-xl font-semibold">Formules</h2>
        <ul className="grid gap-3 sm:grid-cols-2">
          {plans.map((p) => (
            <li key={p.code} className="flex flex-col gap-1 rounded border border-zinc-300 p-4">
              <h3 className="font-semibold">{p.name}</h3>
              <p>{p.free ? "Gratuit" : `${fcfa(p.price_xof)} ${PERIOD_LABELS[p.period] ?? ""}`}</p>
              <ul className="text-sm">
                <li>Annonces actives : {limitLabel(p.limits.active_listings)}</li>
                {p.limits.agents ? <li>Agents : {limitLabel(p.limits.agents)}</li> : null}
                <li>Photos par annonce : {limitLabel(p.limits.photos_per_listing)}</li>
                {p.limits.featured_credits ? <li>Mises en avant incluses : {p.limits.featured_credits}</li> : null}
              </ul>
            </li>
          ))}
        </ul>
        {paid.length === 0 && <p className="text-sm">Les formules payantes seront proposées prochainement.</p>}
      </section>

      <section aria-labelledby="options" className="flex flex-col gap-3">
        <h2 id="options" className="text-xl font-semibold">Mises en avant</h2>
        {products.length === 0 ? (
          <p className="text-sm">Les options de mise en avant seront proposées prochainement.</p>
        ) : (
          <ul className="flex flex-col gap-1">
            {products.map((p) => (
              <li key={p.code}>
                {KIND_LABELS[p.kind]} · {p.duration_days} jours · {fcfa(p.price_xof)}
              </li>
            ))}
          </ul>
        )}
      </section>
    </>
  );
}

export default function Page() {
  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
      <h1 className="text-2xl font-semibold">Offres et mises en avant</h1>
      <p>
        Une mise en avant ou une formule achète de la <strong>visibilité et des services</strong>, jamais un badge de confiance. Le règlement se fait hors ligne
        (Wave, Orange Money, virement ou espèces) et les droits s&apos;ouvrent après <strong>confirmation de l&apos;encaissement</strong> par notre équipe.
      </p>
      <Suspense fallback={<p>Chargement…</p>}>
        <Catalog />
      </Suspense>
      <Link href="/abonnement" className="underline">Gérer mon abonnement</Link>
    </main>
  );
}
