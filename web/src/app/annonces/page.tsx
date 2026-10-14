import type { Metadata } from "next";
import { Suspense } from "react";
import { ResultsList } from "@/components/listings/ResultsList";
import { SaveSearchButton } from "@/components/listings/SaveSearchButton";
import { SearchForm } from "@/components/listings/SearchForm";
import { getNeighborhoods, searchListings } from "@/lib/listings";
import { cleanParams } from "@/lib/params";

export const metadata: Metadata = {
  title: "Rechercher un bien — ChezVous",
  description: "Biens à louer et à vendre à Dakar avec disponibilité datée et Passeport du bien.",
  alternates: { canonical: "/annonces" },
};

async function Search({ searchParams }: { searchParams: PageProps<"/annonces">["searchParams"] }) {
  const params = cleanParams(await searchParams);
  const [result, neighborhoods] = await Promise.all([searchListings(params), getNeighborhoods()]);
  return (
    <>
      <SearchForm values={params} neighborhoods={neighborhoods} />
      <SaveSearchButton params={params} />
      {result ? (
        <ResultsList result={result} params={params} basePath="/annonces" />
      ) : (
        <p role="alert">Les annonces sont momentanément indisponibles. Réessayez dans un instant.</p>
      )}
    </>
  );
}

export default function Page(props: PageProps<"/annonces">) {
  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">Rechercher un bien</h1>
      <Suspense fallback={<p>Chargement…</p>}>
        <Search searchParams={props.searchParams} />
      </Suspense>
    </main>
  );
}
