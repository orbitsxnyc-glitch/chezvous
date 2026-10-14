import Link from "next/link";
import type { SearchParams } from "@/lib/params";
import type { SearchResult } from "@/lib/listings";
import { ListingCard } from "./ListingCard";

/** Résultats paginés par curseur, avec lien « Voir plus » qui conserve les filtres. */
export function ResultsList({ result, params, basePath }: { result: SearchResult; params: SearchParams; basePath: string }) {
  if (result.items.length === 0) {
    return <p role="status">Aucun bien ne correspond à votre recherche. Élargissez vos critères.</p>;
  }
  const next = result.nextCursor ? new URLSearchParams({ ...params, cursor: result.nextCursor }).toString() : null;
  return (
    <>
      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {result.items.map((l) => (
          <li key={l.id}>
            <ListingCard listing={l} />
          </li>
        ))}
      </ul>
      {next && (
        <Link href={`${basePath}?${next}`} className="mt-4 inline-block underline">
          Voir plus de biens
        </Link>
      )}
    </>
  );
}
