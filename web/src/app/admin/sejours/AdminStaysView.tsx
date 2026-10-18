"use client";

import { useEffect, useState } from "react";
import { money } from "@/components/stays/StayCard";
import { ApiError } from "@/lib/api";
import { approveUnit, confirmIntent, confirmRefund, failRefund, fetchAdminIntents, fetchAdminPayouts, fetchAdminRefunds, fetchCases, fetchPendingUnits, fetchReconciliation, payPayout, recordUnitCheck, rejectIntent, resolveCase, CHECK_LABELS, type AdminCase, type AdminIntent, type AdminPayout, type AdminRefund, type HostUnit, type Reconciliation } from "@/lib/stays";

const message = (e: unknown) => (e instanceof ApiError ? e.message : "Une erreur est survenue.");
const TABS = [["units", "Logements à valider"], ["intents", "Règlements à vérifier"], ["refunds", "Remboursements"], ["cases", "Litiges"], ["payouts", "Reversements"], ["report", "Rapprochement"]] as const;
const nf = new Intl.NumberFormat("fr-FR");
const iso = (d: Date) => d.toISOString().slice(0, 10);

export default function AdminStaysView() {
  const [tab, setTab] = useState<(typeof TABS)[number][0]>("intents");
  const [units, setUnits] = useState<HostUnit[]>([]);
  const [intents, setIntents] = useState<AdminIntent[]>([]);
  const [refunds, setRefunds] = useState<AdminRefund[]>([]);
  const [cases, setCases] = useState<AdminCase[]>([]);
  const [payouts, setPayouts] = useState<AdminPayout[]>([]);
  const [report, setReport] = useState<Reconciliation | null>(null);
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");

  const [tick, setTick] = useState(0);

  useEffect(() => {
    let cancelled = false;
    const now = new Date();
    Promise.all([fetchPendingUnits(), fetchAdminIntents("pending"), fetchAdminRefunds(), fetchCases(), fetchAdminPayouts("pending"), fetchReconciliation(iso(new Date(now.getFullYear(), now.getMonth(), 1)), iso(now))])
      .then(([u, i, r, c, p, rep]) => {
        if (cancelled) return;
        setUnits(u.data);
        setIntents(i.data);
        setRefunds(r.data);
        setCases(c.data);
        setPayouts(p.data);
        setReport(rep.data);
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
        {TABS.map(([v, l]) => <button key={v} type="button" aria-pressed={tab === v} onClick={() => setTab(v)} className={`min-h-10 rounded-lg px-4 text-sm font-bold ${tab === v ? "bg-white text-primary shadow-card" : "text-muted"}`}>{l}{v === "units" && units.length ? ` (${units.length})` : v === "intents" && intents.length ? ` (${intents.length})` : v === "refunds" && refunds.length ? ` (${refunds.length})` : v === "cases" && cases.length ? ` (${cases.length})` : v === "payouts" && payouts.length ? ` (${payouts.length})` : v === "report" && report?.anomalies.length ? ` (${report.anomalies.length})` : ""}</button>)}
      </div>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {info && <p role="status" className="rounded-lg bg-success-soft p-3 text-sm text-[#0b6240]">{info}</p>}

      {tab === "units" && (units.length === 0 ? <p className="text-sm text-muted">Aucun logement en attente.</p> : (
        <ul className="flex flex-col gap-2">{units.map((u) => (
          <li key={u.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-white p-4">
            <span><strong>{u.name}</strong><span className="block text-xs text-muted">{u.property?.locality ?? "Quartier manquant"} · {u.rate_plan ? `${money(u.rate_plan.base_nightly_xof)} / nuit` : "Tarif manquant"} · {u.photos?.length ?? 0} photo(s)</span></span>
            <span className="flex flex-wrap gap-2">
              <button type="button" onClick={() => {
                const type = window.prompt(`Type de contrôle (${Object.keys(CHECK_LABELS).join(", ")}) :`)?.trim();
                if (!type) return;
                const ok = window.confirm("Le contrôle est-il RÉUSSI ? (OK = réussi, Annuler = échoué)");
                const note = ok ? undefined : window.prompt("Motif de l'échec (obligatoire) :")?.trim();
                if (!ok && !note) return;
                const until = window.prompt("Valable jusqu'au (AAAA-MM-JJ, facultatif) :")?.trim() || undefined;
                void run(() => recordUnitCheck(u.id, { type, status: ok ? "passed" : "failed", note, valid_until: until }), "Contrôle enregistré.");
              }} className={`${btn} border border-line-strong hover:bg-sand`}>Saisir un contrôle</button>
              <button type="button" onClick={() => run(() => approveUnit(u.id), "Logement validé : il est maintenant visible.")} className={`${btn} bg-primary text-white hover:bg-primary-hover`}>Valider</button>
            </span>
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
      {tab === "cases" && (cases.length === 0 ? <p className="text-sm text-muted">Aucun litige ouvert.</p> : (
        <ul className="flex flex-col gap-2">{cases.map((c) => (
          <li key={c.id} className="flex flex-col gap-2 rounded-xl border border-line bg-white p-4">
            <p><strong>{c.unit}</strong> · {money(c.total_xof)} · ouvert par le {c.opened_by === "guest" ? "voyageur" : "l'hôte"} · gravité {c.severity}</p>
            <p className="text-sm">{c.description}</p>
            <div className="flex flex-wrap gap-2">
              <button type="button" className={`${btn} bg-primary text-white hover:bg-primary-hover`} onClick={() => {
                const note = window.prompt("Décision : séjour conforme. Justification (visible dans le dossier) :")?.trim();
                if (note) void run(() => resolveCase(c.id, { resolution: "completed", note }), "Litige résolu : séjour terminé, reversement libéré.");
              }}>Séjour conforme</button>
              <button type="button" className={`${btn} border border-line-strong hover:bg-sand`} onClick={() => {
                const amount = window.prompt(`Montant à rembourser (FCFA, max ${c.total_xof}) :`)?.trim();
                const note = amount ? window.prompt("Justification de la décision :")?.trim() : undefined;
                if (amount && note) void run(() => resolveCase(c.id, { resolution: "refund_partial", refund_xof: Number(amount), note }), "Remboursement partiel ouvert.");
              }}>Remboursement partiel</button>
              <button type="button" className={`${btn} border border-danger text-danger hover:bg-[#fdecea]`} onClick={() => {
                const note = window.prompt("Remboursement intégral. Justification :")?.trim();
                if (note) void run(() => resolveCase(c.id, { resolution: "refund_full", note }), "Remboursement intégral ouvert.");
              }}>Remboursement intégral</button>
            </div>
          </li>))}</ul>
      ))}

      {tab === "payouts" && (payouts.length === 0 ? <p className="text-sm text-muted">Aucun reversement en attente.</p> : (
        <ul className="flex flex-col gap-2">{payouts.map((p) => (
          <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-white p-4">
            <span><strong>{money(p.amount_xof)}</strong> à reverser · {p.unit}</span>
            <button type="button" className={`${btn} bg-primary text-white hover:bg-primary-hover`} onClick={() => {
              const ref = window.prompt("Référence du reversement effectué (obligatoire) :")?.trim();
              if (ref) void run(() => payPayout(p.id, ref), "Reversement enregistré.");
            }}>J&apos;ai reversé</button>
          </li>))}</ul>
      ))}

      {tab === "report" && report && (
        <div className="flex flex-col gap-3">
          <p className="text-sm text-muted">Période : du {report.period.from} au {report.period.to}. Chiffres issus des règlements, remboursements et reversements enregistrés.</p>
          <dl className="grid gap-2 sm:grid-cols-2">{Object.entries(report.totals).map(([k, v]) => <div key={k} className="flex justify-between gap-3 rounded-lg border border-line bg-white p-3 text-sm"><dt>{k.replaceAll("_", " ")}</dt><dd className="font-bold">{nf.format(v).replace(/[\u202f\u00a0]/g, " ")} FCFA</dd></div>)}</dl>
          <h2 className="text-lg font-extrabold">Écarts à examiner</h2>
          {report.anomalies.length === 0 ? <p className="text-sm text-success">Aucun écart détecté.</p> : (
            <ul className="flex flex-col gap-2">{report.anomalies.map((a, i) => <li key={i} className="rounded-lg border border-[#e5b999] bg-warning-soft p-3 text-sm"><strong>{a.code}</strong> · {a.detail}{a.booking ? ` (séjour ${a.booking})` : ""}</li>)}</ul>
          )}
        </div>
      )}
    </div>
  );
}
