"use client";

import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import { fetchDeliveries, retryDelivery, type Delivery } from "@/lib/admin";
import { ApiError } from "@/lib/api";

const STATUS_LABELS: Record<string, string> = {
  pending: "En attente", retrying: "Nouvelle tentative", sent: "Remis", stubbed: "Simulé (aucun envoi réel)", skipped: "Ignoré", failed: "Échec",
};

export default function DeliveriesView() {
  const [status, setStatus] = useState("failed");
  const [items, setItems] = useState<Delivery[]>([]);
  const [counts, setCounts] = useState<Record<string, number>>({});
  const [cursor, setCursor] = useState<string | null>(null);
  const [error, setError] = useState("");

  const [version, setVersion] = useState(0);

  useEffect(() => {
    let cancelled = false;
    fetchDeliveries(status)
      .then((r) => { if (!cancelled) { setItems(r.data); setCursor(r.meta.next_cursor); setCounts(r.meta.counts); setError(""); } })
      .catch((e) => !cancelled && setError(e instanceof ApiError ? e.message : "Erreur."));
    return () => { cancelled = true; };
  }, [status, version]);

  async function more(next: string) {
    try {
      const r = await fetchDeliveries(status, next);
      setItems((prev) => [...prev, ...r.data]);
      setCursor(r.meta.next_cursor);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur.");
    }
  }

  async function retry(d: Delivery) {
    try { await retryDelivery(d.id); setVersion((v) => v + 1); } catch (e) { setError(e instanceof ApiError ? e.message : "Erreur."); }
  }

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-semibold">Envois de notifications</h1>
      <p className="text-sm text-muted">Seul l&apos;e-mail est un canal réel pour l&apos;instant ; SMS, WhatsApp et notifications push sont simulés et marqués comme tels.</p>
      <select aria-label="Statut" className="self-start rounded-lg border border-line-strong px-3 py-2" value={status} onChange={(e) => setStatus(e.target.value)}>
        <option value="">Tous</option>
        {Object.entries(STATUS_LABELS).map(([k, label]) => <option key={k} value={k}>{label} ({counts[k] ?? 0})</option>)}
      </select>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      <table className="w-full text-left text-sm">
        <thead><tr><th>Type</th><th>Canal</th><th>Statut</th><th>Essais</th><th>Erreur</th><th><span className="sr-only">Actions</span></th></tr></thead>
        <tbody>
          {items.map((d) => (
            <tr key={d.id} className="border-t border-line align-top">
              <td className="py-2">{d.type}</td><td>{d.channel}</td><td>{STATUS_LABELS[d.status] ?? d.status}</td><td>{d.attempts}</td><td>{d.error}</td>
              <td>{d.status === "failed" && <button className="underline" onClick={() => retry(d)}>Relancer</button>}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {items.length === 0 && !error && <p>Aucun envoi pour ce filtre.</p>}
      {cursor && <button className="self-start underline" onClick={() => more(cursor)}>Voir plus</button>}
    </section>
  );
}
