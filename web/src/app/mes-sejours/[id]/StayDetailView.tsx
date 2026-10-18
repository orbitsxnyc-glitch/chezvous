"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import type { FormEvent } from "react";
import { BookingBadge } from "@/components/stays/BookingBadge";
import { money } from "@/components/stays/StayCard";
import { useRequireAuth } from "@/components/useRequireAuth";
import { generalError } from "@/lib/forms";
import { cancelMyBooking, createPaymentIntent, declarePayment, fetchMyBooking, formatDay, needsPayment, type Booking, type PaymentIntent } from "@/lib/stays";

const METHODS = [["wave", "Wave"], ["orange_money", "Orange Money"], ["transfer", "Virement"], ["cash", "Espèces"]] as const;
const when = (iso: string) => new Date(iso).toLocaleString("fr-FR", { dateStyle: "long", timeStyle: "short" });

export default function StayDetailView({ id }: { id: string }) {
  const { user, loading } = useRequireAuth();
  const [booking, setBooking] = useState<Booking | null>(null);
  const [intent, setIntent] = useState<PaymentIntent | null>(null);
  const [method, setMethod] = useState<string>("wave");
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [pending, setPending] = useState(false);

  const [tick, setTick] = useState(0);
  const load = useCallback(() => setTick((t) => t + 1), []);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchMyBooking(id).then((r) => !cancelled && setBooking(r.data)).catch((e) => !cancelled && setError(generalError(e)));
    return () => { cancelled = true; };
  }, [user, id, tick]);

  async function run(action: () => Promise<unknown>, done?: string) {
    setError("");
    setInfo("");
    setPending(true);
    try {
      await action();
      if (done) setInfo(done);
      load();
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPending(false);
    }
  }

  const prepare = () => run(async () => setIntent((await createPaymentIntent(id, method)).data));
  const declare = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const reference = String(new FormData(event.currentTarget).get("reference") ?? "").trim();
    if (!intent) return;
    void run(async () => setIntent((await declarePayment(intent.id, reference)).data), "Merci : nous vérifions la réception de votre règlement. Votre séjour n'est confirmé qu'après cette vérification.");
  };

  if (loading || (!booking && !error)) return <main className="mx-auto max-w-3xl p-6"><p>Chargement…</p></main>;
  if (!booking) return <main className="mx-auto max-w-3xl p-6"><p role="alert">{error}</p><Link href="/mes-sejours" className="underline">Retour</Link></main>;

  const payment = intent;
  const canCancel = ["requested", "accepted", "awaiting_payment", "confirmed"].includes(booking.state);

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-5 px-5 py-8">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted"><Link href="/mes-sejours" className="underline">Mes séjours</Link> <span aria-hidden>/</span> {booking.unit.name}</nav>
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-extrabold">{booking.unit.name}</h1>
        <BookingBadge state={booking.state} />
      </header>
      <p className="text-sm text-muted">{booking.unit.locality ? `${booking.unit.locality} · ` : ""}{formatDay(booking.check_in)} → {formatDay(booking.check_out)} · {booking.guests} voyageur{booking.guests > 1 ? "s" : ""}</p>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {info && <p role="status" className="rounded-lg bg-success-soft p-3 text-sm text-[#0b6240]">{info}</p>}

      <section aria-labelledby="prix" className="rounded-xl border border-line bg-white p-5">
        <h2 id="prix" className="text-lg font-extrabold">Prix</h2>
        <ul className="mt-3 flex flex-col gap-1 text-sm">{booking.lines?.map((l) => <li key={l.code} className="flex justify-between gap-3"><span>{l.label}</span><span className="font-semibold">{money(l.amount_xof)}</span></li>)}</ul>
        <p className="mt-3 flex justify-between border-t border-line pt-3 text-base font-extrabold"><span>Total</span><span>{money(booking.total_xof)}</span></p>
        <p className="mt-2 text-xs text-muted">Politique d&apos;annulation « {booking.cancellation_policy.code} » : {booking.cancellation_policy.tiers.map((t) => `${t.refund_bps / 100} % remboursés jusqu'à ${t.days_before} jour(s) avant l'arrivée`).join(" ; ")}. Au-delà, aucun remboursement.</p>
      </section>

      {booking.state === "requested" && booking.respond_by && <p className="rounded-lg bg-warning-soft p-4 text-sm text-[#714105]">L&apos;hôte a jusqu&apos;au {when(booking.respond_by)} pour répondre. Vos dates ne sont pas encore réservées et rien n&apos;est débité.</p>}
      {booking.reason && <p className="rounded-lg bg-[#f1ece6] p-4 text-sm">Motif : {booking.reason}</p>}

      {needsPayment(booking) && (
        <section aria-labelledby="reglement" className="flex flex-col gap-3 rounded-xl border border-primary/20 bg-primary-soft p-5">
          <h2 id="reglement" className="text-lg font-extrabold">Règlement</h2>
          {booking.payment_due_at && <p className="text-sm">Vos dates sont réservées pour vous jusqu&apos;au <strong>{when(booking.payment_due_at)}</strong>. Passé ce délai, la demande expire.</p>}
          {!payment ? (
            <div className="flex flex-wrap items-end gap-3">
              <label className="flex flex-col gap-1 text-xs font-bold">Moyen de paiement
                <select value={method} onChange={(e) => setMethod(e.target.value)} className="min-h-12 rounded-lg border border-line-strong bg-white px-3 text-sm">{METHODS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
              </label>
              <button type="button" onClick={prepare} disabled={pending} className="min-h-12 rounded-lg bg-primary px-5 font-bold text-white hover:bg-primary-hover disabled:opacity-50">Voir comment payer</button>
            </div>
          ) : (
            <>
              <p className="text-sm">Montant à régler : <strong>{money(payment.amount_xof)}</strong>. Indiquez la référence <strong className="rounded bg-white px-2 py-0.5 font-mono">{payment.reference}</strong> lors du règlement.</p>
              {payment.instructions?.methods.map((m) => <p key={m.method} className="rounded-lg bg-white p-3 text-sm"><strong>{m.label}</strong> : {m.details}</p>)}
              {payment.instructions?.note && <p className="text-xs text-muted">{payment.instructions.note}</p>}
              {payment.status === "pending" ? (
                <p role="status" className="text-sm font-bold text-[#194b82]">Règlement déclaré : en cours de vérification par l&apos;équipe.</p>
              ) : (
                <form onSubmit={declare} className="flex flex-wrap items-end gap-3">
                  <label className="flex flex-col gap-1 text-xs font-bold">Référence de votre transaction
                    <input name="reference" required minLength={3} maxLength={120} className="min-h-12 rounded-lg border border-line-strong bg-white px-3 text-sm" />
                  </label>
                  <button type="submit" disabled={pending} className="min-h-12 rounded-lg border border-primary px-5 font-bold text-primary hover:bg-white disabled:opacity-50">J&apos;ai payé</button>
                </form>
              )}
              <p className="text-xs text-muted">Déclarer un paiement ne confirme pas le séjour : seule la vérification de la réception par ChezVous le confirme.</p>
            </>
          )}
          {!intent && booking.payment && <p className="text-sm">Un règlement est déjà en cours ({booking.payment.status === "pending" ? "déclaré, en vérification" : "à effectuer"}) : rouvrez-le avec « Voir comment payer ».</p>}
        </section>
      )}

      {booking.state === "confirmed" && <p className="rounded-lg bg-success-soft p-4 text-sm text-[#0b6240]">Séjour confirmé. L&apos;adresse exacte et les consignes d&apos;arrivée vous sont communiquées par l&apos;hôte via ChezVous.</p>}

      {booking.timeline && booking.timeline.length > 0 && (
        <section aria-labelledby="historique"><h2 id="historique" className="text-lg font-extrabold">Historique</h2>
          <ol className="mt-2 flex flex-col gap-1 text-sm text-muted">{booking.timeline.map((t, i) => <li key={i}>{when(t.at)} · {t.to}</li>)}</ol>
        </section>
      )}

      {canCancel && (
        <button type="button" disabled={pending} onClick={() => { if (window.confirm("Annuler ce séjour ? Le remboursement éventuel suit la politique d'annulation ci-dessus.")) void run(() => cancelMyBooking(id), "Séjour annulé."); }} className="self-start text-sm text-danger underline">Annuler ce séjour</button>
      )}
    </main>
  );
}
