"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { DeclarePaymentForm } from "@/components/commercial/DeclarePaymentForm";
import { FormMessage, Page } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { ApiError } from "@/lib/api";
import {
  KIND_LABELS, cancelPremium, cancelRequest, declarePremiumPayment, declareSubscriptionPayment, downgrade, fetchMyPremium, fetchMyPublishedListings,
  fetchMySubscription, fetchPlans, fetchProducts, limitLabel, requestPlan, requestPremium, settlementLabel,
  type MySubscription, type PaymentInstructions, type Plan, type PremiumView, type Product,
} from "@/lib/commercial";
import { generalError } from "@/lib/forms";

type Listing = { id: string; reference: string; title: string | null };

function Instructions({ value }: { value: PaymentInstructions }) {
  if (!value) return <p className="text-sm">Les coordonnées de règlement vous seront communiquées par notre équipe.</p>;
  return (
    <div className="rounded border border-dashed border-zinc-400 p-3 text-sm">
      <p className="font-medium">Comment régler (hors ligne)</p>
      <ul>
        {value.methods.map((m) => (
          <li key={m.method}>
            <strong>{m.label}</strong> : {m.details}
          </li>
        ))}
      </ul>
      {value.note && <p>{value.note}</p>}
    </div>
  );
}

export default function SubscriptionView() {
  const { user, loading } = useRequireAuth();
  const [sub, setSub] = useState<(MySubscription & { instructions: PaymentInstructions }) | null>(null);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [premium, setPremium] = useState<PremiumView[]>([]);
  const [listings, setListings] = useState<Listing[]>([]);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState("");
  const [premiumInstructions, setPremiumInstructions] = useState<PaymentInstructions>(null);
  const [version, setVersion] = useState(0);
  const refresh = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchMySubscription()
      .then(async (r) => {
        const [p, pr, pm, ls] = await Promise.all([fetchPlans(), fetchProducts(), fetchMyPremium(), fetchMyPublishedListings()]);
        if (cancelled) return;
        setSub({ ...r.data, instructions: r.payment_instructions });
        setPlans(p.data);
        setProducts(pr.data);
        setPremium(pm.data);
        setListings(ls.data);
      })
      .catch((e) => {
        if (cancelled) return;
        if (e instanceof ApiError && e.status === 403) setForbidden(true);
        else setError(generalError(e));
      });
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

  if (loading || (!sub && !forbidden && !error)) return <Page title="Mon abonnement"><p>Chargement…</p></Page>;
  if (forbidden) return <Page title="Mon abonnement"><p>L&apos;abonnement est réservé aux particuliers et aux gérants d&apos;agence.</p></Page>;
  if (!sub) return <Page title="Mon abonnement"><p role="alert">{error}</p></Page>;

  const over = sub.usage.active_listings > sub.usage.limit;
  const offered = plans.filter((p) => !p.free);

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
      <h1 className="text-2xl font-semibold">Mon abonnement</h1>
      {error && <FormMessage kind="error">{error}</FormMessage>}

      <section aria-labelledby="droits" className="flex flex-col gap-1 rounded border border-zinc-300 p-4">
        <h2 id="droits" className="text-lg font-semibold">Mes droits</h2>
        <p>
          Formule : {sub.current ? sub.current.plan.name : "Gratuit"}
          {sub.current?.ends_at ? ` · jusqu'au ${new Date(sub.current.ends_at).toLocaleDateString("fr-FR")}` : ""}
        </p>
        <p>Annonces actives : {sub.usage.active_listings} / {sub.usage.limit}</p>
        <p className="text-sm">
          Agents : {limitLabel(sub.entitlements.limits.agents)} · Photos par annonce : {limitLabel(sub.entitlements.limits.photos_per_listing)} · Mises en avant incluses : {limitLabel(sub.entitlements.limits.featured_credits)}
        </p>
        {over && (
          <p role="alert" className="text-red-700">
            Vous avez plus d&apos;annonces actives que votre formule n&apos;en permet.
            {sub.downgrade_deadline_at ? ` Choisissez celles à garder avant le ${new Date(sub.downgrade_deadline_at).toLocaleDateString("fr-FR")} ; les plus anciennes seront ensuite mises en pause (jamais supprimées).` : ""}
          </p>
        )}
        {over && listings.length > 0 && (
          <form
            className="mt-2 flex flex-col gap-1 text-sm"
            onSubmit={(e) => {
              e.preventDefault();
              const keep = Array.from(new FormData(e.currentTarget).getAll("keep")).map(String);
              void run(() => downgrade(keep));
            }}
          >
            <p className="font-medium">Annonces à garder (au plus {sub.usage.limit})</p>
            {listings.map((l) => (
              <label key={l.id} className="flex items-center gap-2">
                <input type="checkbox" name="keep" value={l.id} /> {l.title ?? l.reference}
              </label>
            ))}
            <button type="submit" className="self-start rounded border border-zinc-400 px-3 py-1">Mettre les autres en pause</button>
          </form>
        )}
      </section>

      {sub.pending ? (
        <section aria-labelledby="attente" className="flex flex-col gap-3 rounded border border-zinc-300 p-4">
          <h2 id="attente" className="text-lg font-semibold">Demande en attente : {sub.pending.plan.name}</h2>
          <p>Référence à indiquer lors du règlement : <strong>{sub.pending.payment_reference}</strong></p>
          <p>Montant : {new Intl.NumberFormat("fr-FR").format(sub.pending.plan.price_xof)} FCFA</p>
          <p role="status">{settlementLabel(sub.pending.settlement)}</p>
          <p className="text-sm">Aucun avantage n&apos;est accordé avant l&apos;activation par notre équipe, qui suit la confirmation de l&apos;encaissement.</p>
          <Instructions value={sub.instructions} />
          <DeclarePaymentForm defaultAmount={sub.pending.plan.price_xof} onSubmit={async (f) => { await declareSubscriptionPayment(sub.pending!.id, f); refresh(); }} />
          <button type="button" className="self-start text-sm underline" onClick={() => run(() => cancelRequest(sub.pending!.id))}>Annuler ma demande</button>
        </section>
      ) : (
        <section aria-labelledby="formules" className="flex flex-col gap-2">
          <h2 id="formules" className="text-lg font-semibold">Changer de formule</h2>
          {offered.length === 0 ? (
            <p className="text-sm">Les formules payantes seront proposées prochainement. <Link className="underline" href="/offres">Voir les offres</Link></p>
          ) : (
            <ul className="flex flex-col gap-2">
              {offered.map((p) => (
                <li key={p.code} className="flex flex-wrap items-center justify-between gap-2 rounded border border-zinc-300 p-3">
                  <span>{p.name} · {new Intl.NumberFormat("fr-FR").format(p.price_xof)} FCFA</span>
                  <button type="button" className="rounded bg-zinc-900 px-3 py-1 text-white" onClick={() => run(() => requestPlan(p.code))}>Demander</button>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      <section aria-labelledby="mises-en-avant" className="flex flex-col gap-3">
        <h2 id="mises-en-avant" className="text-lg font-semibold">Mettre une annonce en avant</h2>
        {products.length === 0 || listings.length === 0 ? (
          <p className="text-sm">{products.length === 0 ? "Les options de mise en avant seront proposées prochainement." : "Aucune annonce publiée à mettre en avant."}</p>
        ) : (
          <form
            className="flex flex-col gap-2 text-sm"
            onSubmit={(e) => {
              e.preventDefault();
              const f = new FormData(e.currentTarget);
              void run(async () => {
                const r = await requestPremium(String(f.get("listing")), String(f.get("product")));
                setPremiumInstructions(r.payment_instructions);
              });
            }}
          >
            <label className="flex flex-col gap-1">
              Annonce
              <select name="listing" className="rounded border border-zinc-400 px-2 py-1">
                {listings.map((l) => (
                  <option key={l.id} value={l.id}>{l.title ?? l.reference}</option>
                ))}
              </select>
            </label>
            <label className="flex flex-col gap-1">
              Option
              <select name="product" className="rounded border border-zinc-400 px-2 py-1">
                {products.map((p) => (
                  <option key={p.code} value={p.code}>{KIND_LABELS[p.kind]} · {p.duration_days} jours · {new Intl.NumberFormat("fr-FR").format(p.price_xof)} FCFA</option>
                ))}
              </select>
            </label>
            <button type="submit" className="self-start rounded bg-zinc-900 px-4 py-2 text-white">Demander cette option</button>
          </form>
        )}
        {premiumInstructions && <Instructions value={premiumInstructions} />}
        <ul className="flex flex-col gap-2">
          {premium.map((p) => (
            <li key={p.id} className="flex flex-col gap-2 rounded border border-zinc-300 p-3 text-sm">
              <span className="font-medium">{KIND_LABELS[p.kind]} · {p.listing.title ?? p.listing.reference}</span>
              <span>
                {p.status === "active" ? `Active jusqu'au ${p.ends_at ? new Date(p.ends_at).toLocaleDateString("fr-FR") : ""}` : p.status === "pending" ? "En attente" : p.status === "suspended" ? "Suspendue" : p.status === "expired" ? "Terminée" : "Annulée"}
                {p.payment_reference ? ` · Réf. ${p.payment_reference}` : ""} · {settlementLabel(p.settlement)}
              </span>
              {p.status === "pending" && (
                <>
                  <DeclarePaymentForm defaultAmount={p.product.price_xof} onSubmit={async (f) => { await declarePremiumPayment(p.id, f); refresh(); }} />
                  <button type="button" className="self-start underline" onClick={() => run(() => cancelPremium(p.id))}>Annuler</button>
                </>
              )}
            </li>
          ))}
        </ul>
      </section>

      {sub.history.length > 0 && (
        <section aria-labelledby="historique" className="flex flex-col gap-1">
          <h2 id="historique" className="text-lg font-semibold">Historique</h2>
          <ul className="text-sm">
            {sub.history.map((h) => (
              <li key={h.id}>{h.plan.name} · {h.status} · {new Date(h.created_at).toLocaleDateString("fr-FR")}</li>
            ))}
          </ul>
        </section>
      )}
    </main>
  );
}
