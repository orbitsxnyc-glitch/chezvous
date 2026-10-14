import type { Metadata } from "next";
import { Suspense } from "react";
import ListingEditor from "./ListingEditor";

export const metadata: Metadata = { title: "Modifier l'annonce — ChezVous", robots: { index: false } };

export default function Page({ params }: { params: Promise<{ id: string }> }) {
  return (
    <Suspense fallback={<main className="p-6">Chargement…</main>}>
      <ListingEditor params={params} />
    </Suspense>
  );
}
