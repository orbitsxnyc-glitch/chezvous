import Link from "next/link";
import type { SearchParams } from "@/lib/params";
import type { SearchResult } from "@/lib/listings";
import { ListingCard } from "./ListingCard";

/** Résultats paginés par curseur, avec lien « Voir plus » qui conserve les filtres. */
export function ResultsList({ result, params, basePath }: { result: SearchResult; params: SearchParams; basePath: string }) {
  if (result.items.length === 0) {
    return (
      <div role="status" className="rounded-xl border border-dashed border-line-strong bg-white p-8 text-center">
        <p className="font-bold">Aucun bien ne correspond à votre recherche.</p>
        <p className="mt-1 text-sm text-muted">Essayez d&apos;élargir vos critères : un autre quartier, un budget plus haut ou moins de chambres.</p>
      </div>
    );
  }
  const next = result.nextCursor ? new URLSearchParams({ ...params, cursor: result.nextCursor }).toString() : null;
  return (
    <div className="flex flex-col">
      <ul className="flex flex-col gap-4">
        {result.items.map((l) => (
          <li key={l.id}>
            <ListingCard listing={l} layout="row" headingLevel={2} />
          </li>
        ))}
      </ul>
      {next && (
        <Link href={`${basePath}?${next}`} className="mt-6 inline-flex min-h-12 items-center self-center rounded-lg border border-line-strong bg-white px-6 text-sm font-bold text-primary hover:bg-primary-soft">
          Voir plus de biens
        </Link>
      )}
    </div>
  );
}
