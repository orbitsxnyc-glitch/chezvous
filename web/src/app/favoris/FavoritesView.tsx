"use client";

import { useEffect, useState } from "react";
import { ListingCard } from "@/components/listings/ListingCard";
import { Page } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { fetchFavorites, removeFavorite, type FavoriteItem } from "@/lib/engagement";
import { generalError } from "@/lib/forms";

export default function FavoritesView() {
  const { user, loading } = useRequireAuth();
  const [items, setItems] = useState<FavoriteItem[] | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchFavorites()
      .then((r) => !cancelled && setItems(r.data))
      .catch((e) => !cancelled && setError(generalError(e)));
    return () => {
      cancelled = true;
    };
  }, [user]);

  async function remove(item: FavoriteItem) {
    try {
      await removeFavorite(item.listing.id);
      setItems((prev) => prev?.filter((f) => f.listing.id !== item.listing.id) ?? null);
    } catch (e) {
      setError(generalError(e));
    }
  }

  if (loading || items === null) return <Page title="Mes favoris">{error ? <p role="alert">{error}</p> : <p>Chargement…</p>}</Page>;

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">Mes favoris</h1>
      {error && <p role="alert">{error}</p>}
      {items.length === 0 ? (
        <p>Aucun favori. Ajoutez des biens depuis leur fiche pour être prévenu d&apos;une baisse de prix.</p>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((f) => (
            <li key={f.listing.id} className="flex flex-col gap-1">
              {!f.available && <p className="text-sm text-red-700">Ce bien n&apos;est plus disponible.</p>}
              <ListingCard listing={f.listing} />
              <button type="button" className="self-start text-sm underline" onClick={() => remove(f)}>Retirer des favoris</button>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
