"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useAuth } from "@/components/AuthProvider";
import { money } from "@/components/stays/StayCard";
import { formatPrice } from "@/lib/format";
import { generalError } from "@/lib/forms";
import { createQuote, fetchStay, nightsBetween, requestBooking, type Pricing } from "@/lib/stays";

const control = "min-h-12 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink";

/** Panneau de demande : dates et voyageurs, prix détaillé calculé par le serveur, puis demande (aucun débit à ce stade). */
export function StayBooking({ unitId, capacity, minNights, nightly, initial }: { unitId: string; capacity: number; minNights: number; nightly: number | null; initial: { check_in: string; check_out: string; guests: string } }) {
  const router = useRouter();
  const { user, loading } = useAuth();
  const [checkIn, setCheckIn] = useState(initial.check_in);
  const [checkOut, setCheckOut] = useState(initial.check_out);
  const [guests, setGuests] = useState(initial.guests);
  const [pricing, setPricing] = useState<Pricing | null>(null);
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);
  const today = new Date().toISOString().slice(0, 10);
  const nights = nightsBetween(checkIn, checkOut);

  async function check() {
    setError("");
    setPricing(null);
    if (nights < 1) return setError("Choisissez une date de départ après la date d'arrivée.");
    setPending(true);
    try {
      const r = await fetchStay(unitId, { check_in: checkIn, check_out: checkOut, guests });
      setPricing(r.data.pricing ?? null);
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPending(false);
    }
  }

  async function request() {
    setError("");
    setPending(true);
    try {
      const quote = await createQuote({ unit: unitId, check_in: checkIn, check_out: checkOut, guests: Number(guests) });
      // Une clé d'idempotence par intention : un double clic ne crée jamais deux demandes.
      const booking = await requestBooking({ quote: quote.data.id, idempotency_key: crypto.randomUUID() });
      router.push(`/mes-sejours/${booking.data.id}`);
    } catch (e) {
      setError(generalError(e));
      setPending(false);
    }
  }

  return (
    <aside aria-labelledby="reserver" className="flex flex-col gap-3 rounded-2xl border border-primary/15 bg-white p-5 shadow-panel lg:sticky lg:top-24">
      <h2 id="reserver" className="text-lg font-extrabold">{nightly !== null ? formatPrice(nightly, "rent_furnished_short") : "Tarif à venir"}</h2>
      <div className="grid grid-cols-2 gap-2">
        <label className="flex flex-col gap-1 text-xs font-bold">Arrivée<input type="date" min={today} value={checkIn} onChange={(e) => { setCheckIn(e.target.value); setPricing(null); }} className={control} /></label>
        <label className="flex flex-col gap-1 text-xs font-bold">Départ<input type="date" min={checkIn || today} value={checkOut} onChange={(e) => { setCheckOut(e.target.value); setPricing(null); }} className={control} /></label>
      </div>
      <label className="flex flex-col gap-1 text-xs font-bold">Voyageurs (max. {capacity})<input type="number" min={1} max={capacity} value={guests} onChange={(e) => { setGuests(e.target.value); setPricing(null); }} className={control} /></label>
      {minNights > 1 && <p className="text-xs text-muted">Durée minimale : {minNights} nuits.</p>}
      <button type="button" onClick={check} disabled={pending} className="min-h-12 rounded-lg border border-primary px-4 font-bold text-primary hover:bg-primary-soft disabled:opacity-50">Voir le prix et la disponibilité</button>
      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      {pricing && !pricing.available && <p role="alert" className="rounded-lg bg-warning-soft p-3 text-sm text-[#714105]">{pricing.message}</p>}
      {pricing?.available && (
        <div className="flex flex-col gap-2" aria-live="polite">
          <ul className="flex flex-col gap-1 text-sm">
            {pricing.lines.map((l) => <li key={l.code} className="flex justify-between gap-3"><span>{l.label}</span><span className="font-semibold">{money(l.amount_xof)}</span></li>)}
          </ul>
          <p className="flex justify-between border-t border-line pt-2 text-base font-extrabold"><span>Total</span><span>{money(pricing.total_xof)}</span></p>
          <p className="text-xs text-muted">{pricing.rules.taxes_notice}</p>
          {loading ? null : user ? (
            <button type="button" onClick={request} disabled={pending} className="min-h-12 rounded-lg bg-primary px-4 font-bold text-white shadow-card hover:bg-primary-hover disabled:opacity-50">{pending ? "Envoi…" : "Envoyer ma demande"}</button>
          ) : (
            <Link href="/connexion" className="flex min-h-12 items-center justify-center rounded-lg bg-primary px-4 font-bold text-white hover:bg-primary-hover">Se connecter pour demander</Link>
          )}
          <p className="text-xs text-muted">Aucun débit à cette étape : l&apos;hôte doit d&apos;abord accepter, puis vous disposez d&apos;un délai pour régler.</p>
        </div>
      )}
    </aside>
  );
}
