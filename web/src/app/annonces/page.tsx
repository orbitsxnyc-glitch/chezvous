import type { Metadata } from "next";
import Link from "next/link";
import { connection } from "next/server";
import { Suspense } from "react";
import { ResultsList } from "@/components/listings/ResultsList";
import { SaveSearchButton } from "@/components/listings/SaveSearchButton";
import { FiltersShell } from "@/components/listings/FiltersShell";
import { SearchForm } from "@/components/listings/SearchForm";
import { getNeighborhoods, searchListings } from "@/lib/listings";
import { cleanParams } from "@/lib/params";

export const metadata: Metadata = {
  title: "Rechercher un bien — ChezVous",
  description: "Biens à louer et à vendre à Dakar avec disponibilité datée et Passeport du bien.",
  alternates: { canonical: "/annonces" },
};

async function Search({ searchParams }: { searchParams: PageProps<"/annonces">["searchParams"] }) {
  await connection(); // la fraîcheur affichée dépend de l'heure courante : rendu à la demande
  const params = cleanParams(await searchParams);
  const [result, neighborhoods] = await Promise.all([searchListings(params), getNeighborhoods()]);
  return (
    <div className="grid items-start gap-6 lg:grid-cols-[18rem_1fr]">
      <aside className="lg:sticky lg:top-24">
        <FiltersShell count={Object.keys(params).filter((k) => k !== "cursor" && k !== "sort").length}>
          <SearchForm values={params} neighborhoods={neighborhoods} />
        </FiltersShell>
      </aside>
      <section aria-label="Résultats" className="flex min-w-0 flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-muted">{result ? `${result.items.length} bien${result.items.length > 1 ? "s" : ""} affiché${result.items.length > 1 ? "s" : ""}${result.nextCursor ? " (d'autres suivent)" : ""}` : ""}</p>
          <SaveSearchButton params={params} />
        </div>
        {result ? (
          <ResultsList result={result} params={params} basePath="/annonces" />
        ) : (
          <p role="alert" className="rounded-xl border border-line bg-white p-6">Les annonces sont momentanément indisponibles. Réessayez dans un instant.</p>
        )}
      </section>
    </div>
  );
}

export default function Page(props: PageProps<"/annonces">) {
  return (
    <main className="mx-auto flex w-full max-w-6xl flex-col gap-5 px-5 py-8">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted">
        <Link href="/" className="underline">Accueil</Link> <span aria-hidden>/</span> Rechercher
      </nav>
      <h1 className="text-3xl font-extrabold">Rechercher un bien</h1>
      <Suspense fallback={<p>Chargement…</p>}>
        <Search searchParams={props.searchParams} />
      </Suspense>
    </main>
  );
}
