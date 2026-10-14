import { notFound } from "next/navigation";
import { Suspense } from "react";
import type { SearchParams } from "@/lib/params";
import { cleanParams } from "@/lib/params";
import { getNeighborhoods, searchListings } from "@/lib/listings";
import { SEO_ROUTES, seoPath, seoTitle, typeBySlug, type SeoTransaction } from "@/lib/seo";
import { ResultsList } from "./ResultsList";

export type SeoParams = { transaction: SeoTransaction; type: string; locality: string };

export async function resolveSeo({ transaction, type, locality }: SeoParams) {
  const typePage = typeBySlug(type);
  const neighborhood = (await getNeighborhoods()).find((n) => n.slug === locality);
  if (!typePage || !neighborhood) return null;
  const params: SearchParams = { transaction, type: typePage.code, locality: neighborhood.slug };
  return { typePage, neighborhood, params, title: seoTitle(transaction, typePage.plural, neighborhood.name), path: seoPath(transaction, typePage.slug, neighborhood.slug) };
}

export async function seoMetadata(p: SeoParams) {
  const seo = await resolveSeo(p);
  if (!seo) notFound();
  const result = await searchListings(seo.params);
  const count = result?.items.length ?? 0;
  const description = `${seo.title} : ${count > 0 ? "biens avec disponibilité datée et Passeport du bien" : "aucun bien pour le moment"}. Trouvez, vérifiez, visitez avec ChezVous.`;
  return {
    title: `${seo.title} — ChezVous`,
    description,
    alternates: { canonical: seo.path },
    // Une page vide n'a pas de contenu utile : on évite de la faire indexer.
    robots: count === 0 ? { index: false, follow: true } : undefined,
    openGraph: { title: seo.title, description, url: seo.path, type: "website" as const, locale: "fr_SN" },
  };
}

async function Content({ p }: { p: SeoParams }) {
  const seo = await resolveSeo(p);
  if (!seo) notFound();
  const result = await searchListings(cleanParams(seo.params));
  return (
    <>
      <h1 className="text-2xl font-semibold">{seo.title}</h1>
      {result ? <ResultsList result={result} params={seo.params} basePath="/annonces" /> : <p role="alert">Les annonces sont momentanément indisponibles.</p>}
      <p className="text-sm text-zinc-600">
        Autres recherches : <a className="underline" href={`/${SEO_ROUTES[p.transaction].segment}/appartements/${p.locality}`}>appartements à {seo.neighborhood.name}</a>
      </p>
    </>
  );
}

export function SeoListingPage({ p }: { p: SeoParams }) {
  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-6">
      <Suspense fallback={<p>Chargement…</p>}>
        <Content p={p} />
      </Suspense>
    </main>
  );
}
