"use client";

import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import { fetchDashboard, type Dashboard } from "@/lib/admin";
import { ApiError } from "@/lib/api";
import { formatXof } from "@/lib/format";

function Card({ label, value, note }: { label: string; value: string | number; note?: string }) {
  return (
    <div className="rounded border border-zinc-300 p-4">
      <p className="text-sm text-zinc-600">{label}</p>
      <p className="text-2xl font-semibold">{value}</p>
      {note && <p className="text-xs text-zinc-600">{note}</p>}
    </div>
  );
}

export default function DashboardView() {
  const [d, setD] = useState<Dashboard | null>(null);
  const [error, setError] = useState("");
  useEffect(() => {
    fetchDashboard().then((r) => setD(r.data)).catch((e) => setError(e instanceof ApiError ? e.message : "Erreur."));
  }, []);
  if (error) return <FormMessage kind="error">{error}</FormMessage>;
  if (!d) return <p>Chargement…</p>;
  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-semibold">Tableau de bord</h1>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Card label="Inscriptions (7 jours)" value={d.registrations.last_7_days} note={`${d.registrations.total} au total`} />
        <Card label="Annonces publiées" value={d.listings.published} note={`${d.listings.pending_moderation} en modération`} />
        <Card label="Signalements ouverts" value={d.moderation.open_reports} note={`${d.moderation.pending_agency_verifications} vérifications d'agence`} />
        <Card label="Demandes (30 jours)" value={d.inquiries.sent_last_30_days} note={`${d.inquiries.open} en cours`} />
        <Card label="Abonnements actifs" value={d.commercial.active_subscriptions} note={`${d.commercial.pending_requests} demandes en attente`} />
        <Card label="Encaissé ce mois (confirmé)" value={formatXof(d.commercial.confirmed_revenue_month_xof)} />
        <Card label="Déclaré, non confirmé" value={formatXof(d.commercial.declared_unconfirmed_xof)} note={`${d.commercial.declared_unconfirmed_count} règlements`} />
        <Card label="Notifications en échec (24 h)" value={d.notifications.failed_last_24h} />
      </div>
    </section>
  );
}
