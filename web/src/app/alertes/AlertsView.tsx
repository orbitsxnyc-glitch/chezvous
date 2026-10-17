"use client";

import { useCallback, useEffect, useState } from "react";
import { FormMessage, Page } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { deleteSavedSearch, fetchSavedSearches, updateSavedSearch, type SavedSearch } from "@/lib/engagement";
import { generalError } from "@/lib/forms";

const FREQUENCY = { immediate: "Immédiate", daily: "Quotidienne" } as const;

export default function AlertsView() {
  const { user, loading } = useRequireAuth();
  const [items, setItems] = useState<SavedSearch[] | null>(null);
  const [error, setError] = useState("");
  const [version, setVersion] = useState(0);
  const refresh = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchSavedSearches()
      .then((r) => !cancelled && setItems(r.data))
      .catch((e) => !cancelled && setError(generalError(e)));
    return () => {
      cancelled = true;
    };
  }, [user, version]);

  async function run(fn: () => Promise<unknown>) {
    setError("");
    try {
      await fn();
      refresh();
    } catch (e) {
      setError(generalError(e));
    }
  }

  if (loading || items === null) return <Page title="Mes alertes">{error ? <p role="alert">{error}</p> : <p>Chargement…</p>}</Page>;

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">Mes alertes de recherche</h1>
      <p className="text-sm">Enregistrez une recherche depuis la page « Rechercher un bien » pour être prévenu des nouvelles annonces.</p>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {items.length === 0 ? (
        <p>Aucune alerte enregistrée.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {items.map((s) => (
            <li key={s.id} className="flex flex-col gap-1 rounded-lg border border-line p-3">
              <span className="font-medium">{s.name}</span>
              <span className="text-sm">{Object.entries(s.criteria).map(([k, v]) => `${k}: ${String(v)}`).join(" · ")}</span>
              <span className="flex flex-wrap items-center gap-3 text-sm">
                <label className="flex items-center gap-1">
                  Fréquence
                  <select value={s.frequency} onChange={(e) => run(() => updateSavedSearch(s.id, { frequency: e.target.value as SavedSearch["frequency"] }))} className="rounded-lg border border-line-strong px-1">
                    {Object.entries(FREQUENCY).map(([v, l]) => (
                      <option key={v} value={v}>{l}</option>
                    ))}
                  </select>
                </label>
                <label className="flex items-center gap-1">
                  <input type="checkbox" checked={s.is_active} onChange={(e) => run(() => updateSavedSearch(s.id, { is_active: e.target.checked }))} /> Active
                </label>
                <button type="button" className="underline" onClick={() => run(() => deleteSavedSearch(s.id))}>Supprimer</button>
              </span>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
