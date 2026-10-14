"use client";

import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import { actOnReport, fetchReports, type AdminReport, type ReportAction } from "@/lib/admin";
import { ApiError } from "@/lib/api";

const ACTIONS: [ReportAction, string][] = [
  ["hide", "Masquer l'annonce"], ["warn", "Avertir l'annonceur"], ["suspend-advertiser", "Suspendre l'annonceur"], ["dismiss", "Classer sans suite"],
];

export default function ReportsView() {
  const [items, setItems] = useState<AdminReport[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [note, setNote] = useState<Record<number, string>>({});

  useEffect(() => {
    let cancelled = false;
    fetchReports("open")
      .then((r) => { if (!cancelled) { setItems(r.data); setCursor(r.meta.next_cursor); setError(""); } })
      .catch((e) => !cancelled && setError(e instanceof ApiError ? e.message : "Erreur."))
      .finally(() => !cancelled && setLoading(false));
    return () => { cancelled = true; };
  }, []);

  async function more(next: string) {
    try {
      const r = await fetchReports("open", next);
      setItems((prev) => [...prev, ...r.data]);
      setCursor(r.meta.next_cursor);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur.");
    }
  }

  async function act(r: AdminReport, action: ReportAction) {
    const text = note[r.id]?.trim() ?? "";
    if (text.length < 3) return setError("Ajoutez une note d'au moins 3 caractères : elle est conservée dans le journal d'audit.");
    if (action === "suspend-advertiser" && !window.confirm("Suspendre l'annonceur coupe son accès et masque ses annonces. Confirmer ?")) return;
    try {
      await actOnReport(r.id, action, text);
      setItems((prev) => prev.filter((x) => x.id !== r.id));
      setError("");
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur.");
    }
  }

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-semibold">Signalements ouverts</h1>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {loading ? <p>Chargement…</p> : items.length === 0 ? <p>Aucun signalement ouvert.</p> : (
        <ul className="flex flex-col gap-3">
          {items.map((r) => (
            <li key={r.id} className="flex flex-col gap-2 rounded border border-zinc-300 p-4">
              <p className="font-medium">{r.reason.label} — {r.listing.reference} <span className="text-sm text-zinc-600">({r.listing.status})</span></p>
              <p className="text-sm">{r.listing.title ?? "Sans titre"} · {r.open_reports_on_listing} signalement(s) ouvert(s) sur cette annonce · par {r.reporter}</p>
              {r.comment && <p className="text-sm italic">« {r.comment} »</p>}
              <label className="text-sm" htmlFor={`note-${r.id}`}>Note (obligatoire, journalisée)</label>
              <input id={`note-${r.id}`} className="rounded border border-zinc-400 px-3 py-2" value={note[r.id] ?? ""} onChange={(e) => setNote({ ...note, [r.id]: e.target.value })} />
              <div className="flex flex-wrap gap-2">
                {ACTIONS.map(([a, label]) => (
                  <button key={a} className="rounded border border-zinc-500 px-3 py-1 text-sm" onClick={() => act(r, a)}>{label}</button>
                ))}
              </div>
            </li>
          ))}
        </ul>
      )}
      {cursor && <button className="self-start underline" onClick={() => more(cursor)}>Voir plus</button>}
    </section>
  );
}
