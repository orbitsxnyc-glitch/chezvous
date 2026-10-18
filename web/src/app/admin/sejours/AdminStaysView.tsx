"use client";

import { useEffect, useState } from "react";
import { money } from "@/components/stays/StayCard";
import { ApiError } from "@/lib/api";
import { approveUnit, confirmIntent, confirmRefund, failRefund, fetchAdminIntents, fetchAdminRefunds, fetchPendingUnits, rejectIntent, type AdminIntent, type AdminRefund, type HostUnit } from "@/lib/stays";

const message = (e: unknown) => (e instanceof ApiError ? e.message : "Une erreur est survenue.");
const TABS = [["units", "Logements à valider"], ["intents", "Règlements à vérifier"], ["refunds", "Remboursements"]] as const;

export default function AdminStaysView() {
  const [tab, setTab] = useState<(typeof TABS)[number][0]>("intents");
  const [units, setUnits] = useState<HostUnit[]>([]);
  const [intents, setIntents] = useState<AdminIntent[]>([]);
  const [refunds, setRefunds] = useState<AdminRefund[]>([]);
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");

  const [tick, setTick] = useState(0);

  useEffect(() => {
    let cancelled = false;
    Promise.all([fetchPendingUnits(), fetchAdminIntents("pending"), fetchAdminRefunds()])
      .then(([u, i, r]) => {
        if (cancelled) return;
        setUnits(u.data);
        setIntents(i.data);
        setRefunds(r.data);
      })
      .catch((e) => !cancelled && setError(message(e)));
    return () => { cancelled = true; };
  }, [tick]);

  async function run(action: () => Promise<unknown>, done: string) {
    setError("");
    setInfo("");
    try {
      await action();
      setInfo(done);
      setTick((t) => t + 1);
    } catch (e) {
      setError(message(e));
    }
  }

  const btn = "min-h-10 rounded-lg px-3 text-sm font-bold";
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-2xl font-extrabold">Séjours</h1>
      <div role="group" aria-label="Rubrique" className="flex flex-wrap gap-1 self-start rounded-xl bg-sand p-1">
        {TABS.map(([v, l]) => <button key={v} type="button" aria-pressed={tab === v} onClick={() => setTab(v)} className={`min-h-10 rounded-lg px-4 text-sm font-bold ${tab === v ? "bg-white text-primary shadow-card" : "text-muted"}`}>{l}{v === "units" && units.length ? ` (${units.length})` : v === "intents" && intents.length ? ` (${intents.length})` : v === "refunds" && refunds.length ? ` (${refunds.length})` : ""}</button>)}
      </div>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {info && <p role="status" className="rounded-lg bg-success-soft p-3 text-sm text-[#0b6240]">{info}</p>}

      {tab === "units" && (units.length === 0 ? <p className="text-sm text-muted">Aucun logement en attente.</p> : (
        <ul className="flex flex-col gap-2">{units.map((u) => (
          <li key={u.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-white p-4">
            <span><strong>{u.name}</strong><span className="block text-xs text-muted">{u.property?.locality ?? "Quartier manquant"} · {u.rate_plan ? `${money(u.rate_plan.base_nightly_xof)} / nuit` : "Tarif manquant"} · {u.photos?.length ?? 0} photo(s)</span></span>
            <button type="button" onClick={() => run(() => approveUnit(u.id), "Logement validé : il est maintenant visible.")} className={`${btn} bg-primary text-white hover:bg-primary-hover`}>Valider</button>
          </li>))}</ul>
      ))}

      {tab === "intents" && (intents.length === 0 ? <p className="text-sm text-muted">Aucun règlement en attente.</p> : (
        <ul className="flex flex-col gap-2">{intents.map((i) => (
          <li key={i.id} className="flex flex-col gap-2 rounded-xl border border-line bg-white p-4">
            <p><strong>{money(i.amount_xof)}</strong> · {i.method} · réf. <span className="font-mono">{i.reference}</span> · {i.guest_first_name}</p>
            <p className="text-xs text-muted">{i.declared_reference ? `Référence déclarée par le voyageur : ${i.declared_reference}` : "Pas encore déclaré par le voyageur."} Vérifiez la réception sur votre compte avant de confirmer.</p>
            <div className="flex flex-wrap gap-2">
              <button type="button" className={`${btn} bg-primary text-white hover:bg-primary-hover`} onClick={() => {
                const v = window.prompt(`Montant réellement reçu (FCFA). Attendu : ${i.amount_xof}`, String(i.amount_xof));
                if (v) void run(() => confirmIntent(i.id, Number(v)), "Règlement confirmé : séjour confirmé.");
              }}>Confirmer la réception</button>
              <button type="button" className={`${btn} border border-line-strong hover:bg-sand`} onClick={() => {
                const reason = window.prompt("Motif du rejet (ex. aucun paiement reçu) :")?.trim();
                if (reason) void run(() => rejectIntent(i.id, reason), "Règlement rejeté.");
              }}>Rejeter</button>
            </div>
          </li>))}</ul>
      ))}

      {tab === "refunds" && (refunds.length === 0 ? <p className="text-sm text-muted">Aucun remboursement à traiter.</p> : (
        <ul className="flex flex-col gap-2">{refunds.map((r) => (
          <li key={r.id} className="flex flex-col gap-2 rounded-xl border border-line bg-white p-4">
            <p><strong>{money(r.amount_xof)}</strong> à rembourser · {r.method} · réf. paiement <span className="font-mono">{r.payment_reference}</span> · motif {r.reason}</p>
            <div className="flex flex-wrap gap-2">
              <button type="button" className={`${btn} bg-primary text-white hover:bg-primary-hover`} onClick={() => {
                const ref = window.prompt("Référence du remboursement effectué (obligatoire) :")?.trim();
                if (ref) void run(() => confirmRefund(r.id, ref), "Remboursement enregistré.");
              }}>J&apos;ai remboursé</button>
              <button type="button" className={`${btn} border border-line-strong hover:bg-sand`} onClick={() => {
                const reason = window.prompt("Raison de l'échec :")?.trim();
                if (reason) void run(() => failRefund(r.id, reason), "Échec enregistré.");
              }}>Échec</button>
            </div>
          </li>))}</ul>
      ))}
    </div>
  );
}
