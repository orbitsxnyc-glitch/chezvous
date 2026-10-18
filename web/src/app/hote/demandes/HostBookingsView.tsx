"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { BookingBadge } from "@/components/stays/BookingBadge";
import { money } from "@/components/stays/StayCard";
import { useRequireAuth } from "@/components/useRequireAuth";
import { generalError } from "@/lib/forms";
import { fetchHostBookings, formatDay, hostBookingAction, hostBookingStep, openDispute, type Booking } from "@/lib/stays";

const FILTERS = [["", "Toutes"], ["requested", "À traiter"], ["accepted", "À régler"], ["confirmed", "Confirmées"], ["checked_in", "En cours"]] as const;
const when = (iso: string) => new Date(iso).toLocaleString("fr-FR", { dateStyle: "long", timeStyle: "short" });

export default function HostBookingsView() {
  const { user, loading } = useRequireAuth();
  const [state, setState] = useState("requested");
  const [items, setItems] = useState<Booking[] | null>(null);
  const [error, setError] = useState("");
  const [pendingId, setPendingId] = useState("");

  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchHostBookings(state).then((r) => !cancelled && setItems(r.data)).catch((e) => !cancelled && setError(generalError(e)));
    return () => { cancelled = true; };
  }, [user, state, tick]);

  async function step(id: string, kind: "check-in" | "complete") {
    setError("");
    setPendingId(id);
    try {
      await hostBookingStep(id, kind);
      setTick((t) => t + 1);
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPendingId("");
    }
  }

  async function dispute(id: string) {
    const d = window.prompt("Décrivez le problème (10 caractères minimum) :")?.trim();
    if (!d) return;
    setError("");
    setPendingId(id);
    try {
      await openDispute(id, "host", d);
      setTick((t) => t + 1);
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPendingId("");
    }
  }

  async function act(id: string, action: "accept" | "decline" | "cancel") {
    let reason: string | undefined;
    if (action !== "accept") {
      reason = window.prompt(action === "decline" ? "Motif du refus (visible par le voyageur) :" : "Motif de l'annulation (visible par le voyageur) :")?.trim();
      if (!reason) return;
    }
    setError("");
    setPendingId(id);
    try {
      await hostBookingAction(id, action, reason);
      setTick((t) => t + 1);
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPendingId("");
    }
  }

  return (
    <main className="mx-auto flex w-full max-w-4xl flex-col gap-5 px-5 py-8">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted"><Link href="/hote" className="underline">Espace hôte</Link> <span aria-hidden>/</span> Demandes</nav>
      <h1 className="text-3xl font-extrabold">Demandes de séjour</h1>
      <div role="group" aria-label="Filtrer par état" className="flex flex-wrap gap-1 rounded-xl bg-sand p-1 self-start">
        {FILTERS.map(([v, l]) => <button key={v} type="button" aria-pressed={state === v} onClick={() => { setItems(null); setState(v); }} className={`min-h-10 rounded-lg px-4 text-sm font-bold ${state === v ? "bg-white text-primary shadow-card" : "text-muted"}`}>{l}</button>)}
      </div>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {loading || items === null ? <p>Chargement…</p> : items.length === 0 ? <p className="text-sm text-muted">Aucune demande dans cette catégorie.</p> : (
        <ul className="flex flex-col gap-3">
          {items.map((b) => (
            <li key={b.id} className="flex flex-col gap-2 rounded-xl border border-line bg-white p-4 shadow-card">
              <div className="flex flex-wrap items-center justify-between gap-2"><strong>{b.unit.name}</strong><BookingBadge state={b.state} /></div>
              <p className="text-sm text-muted">{b.guest.first_name} · {formatDay(b.check_in)} → {formatDay(b.check_out)} · {b.guests} voyageur{b.guests > 1 ? "s" : ""}</p>
              <p className="text-sm font-bold text-primary">{money(b.total_xof)}</p>
              {b.state === "requested" && b.respond_by && <p className="text-xs text-[#714105]">À traiter avant le {when(b.respond_by)}.</p>}
              {b.state === "accepted" || b.state === "awaiting_payment" ? <p className="text-xs text-muted">Dates réservées pour le voyageur{b.payment_due_at ? ` jusqu'au ${when(b.payment_due_at)}` : ""}, en attente de règlement.</p> : null}
              <div className="flex flex-wrap gap-2">
                {b.state === "requested" && <>
                  <button type="button" disabled={pendingId === b.id} onClick={() => act(b.id, "accept")} className="min-h-11 rounded-lg bg-primary px-4 font-bold text-white hover:bg-primary-hover disabled:opacity-50">Accepter</button>
                  <button type="button" disabled={pendingId === b.id} onClick={() => act(b.id, "decline")} className="min-h-11 rounded-lg border border-line-strong px-4 font-bold hover:bg-sand">Refuser</button>
                </>}
                {(b.state === "confirmed" || b.state === "checked_in") && <>
                  {b.state === "confirmed" && <button type="button" disabled={pendingId === b.id} onClick={() => step(b.id, "check-in")} className="min-h-11 rounded-lg border border-line-strong px-4 font-bold hover:bg-sand">Arrivée du voyageur</button>}
                  <button type="button" disabled={pendingId === b.id} onClick={() => step(b.id, "complete")} className="min-h-11 rounded-lg bg-primary px-4 font-bold text-white hover:bg-primary-hover disabled:opacity-50">Séjour terminé</button>
                  <button type="button" disabled={pendingId === b.id} onClick={() => dispute(b.id)} className="min-h-11 rounded-lg border border-line-strong px-4 font-bold hover:bg-sand">Signaler un problème</button>
                </>}
                {(b.state === "accepted" || b.state === "awaiting_payment" || b.state === "confirmed") && <button type="button" disabled={pendingId === b.id} onClick={() => act(b.id, "cancel")} className="min-h-11 rounded-lg border border-danger px-4 font-bold text-danger hover:bg-[#fdecea]">Annuler le séjour</button>}
              </div>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
