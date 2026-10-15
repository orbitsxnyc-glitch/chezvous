"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { ApiError } from "@/lib/api";
import { formatPrice } from "@/lib/format";
import { fetchMyListings, STATUS_LABELS, type MyListing } from "@/lib/myListings";

export default function MyListingsView() {
  const { user, loading } = useRequireAuth();
  const [items, setItems] = useState<MyListing[] | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchMyListings()
      .then((r) => !cancelled && setItems(r.data))
      .catch((e) => !cancelled && setError(e instanceof ApiError && e.status === 403 ? "Votre compte ne publie pas d'annonces." : "Impossible de charger vos annonces."));
    return () => {
      cancelled = true;
    };
  }, [user]);

  if (loading || !user) return <main className="p-6">Chargement…</main>;
  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Mes annonces</h1>
        <Link href="/mes-annonces/nouvelle" className="rounded-lg bg-primary hover:bg-primary-hover px-4 py-2 text-white">Nouvelle annonce</Link>
      </div>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {items === null && !error && <p>Chargement…</p>}
      {items?.length === 0 && <p>Vous n&apos;avez pas encore d&apos;annonce.</p>}
      <ul className="flex flex-col gap-3">
        {items?.map((l) => (
          <li key={l.id} className="rounded-lg border border-line p-4">
            <Link href={`/mes-annonces/${l.id}`} className="font-medium underline">{l.title ?? "Annonce sans titre"}</Link>
            <p className="text-sm">{l.reference} · {STATUS_LABELS[l.status] ?? l.status} · {formatPrice(l.price_xof, l.transaction)}</p>
            {l.status === "rejected" && l.rejection?.comment && <p className="text-sm text-danger">Motif : {l.rejection.comment}</p>}
          </li>
        ))}
      </ul>
    </main>
  );
}
