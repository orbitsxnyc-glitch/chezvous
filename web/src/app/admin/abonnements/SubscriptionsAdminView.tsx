"use client";

import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import {
  canActivate, confirmPayment, fetchPremium, fetchSubscriptions, lifecycle, METHOD_LABELS, PAYMENT_LABELS, recordPayment, rejectPayment,
  SETTLEMENT_LABELS, STATUS_LABELS, type AdminPremium, type AdminSubscription, type Kind, type PaymentRow, type Settlement,
} from "@/lib/adminCommercial";
import { ApiError } from "@/lib/api";
import { formatXof } from "@/lib/format";

const msg = (e: unknown) => (e instanceof ApiError ? e.message : "Erreur.");
const SETTLEMENT_TONE: Record<Settlement, string> = {
  unpaid: "bg-[#f1ece6] text-[#5a4f47]", declared: "bg-warning-soft text-[#714105]", confirmed: "bg-success-soft text-[#0b6240]", not_required: "bg-info-soft text-[#174f8d]",
};

type Row = (AdminSubscription | AdminPremium) & { id: string };
const isSub = (r: Row): r is AdminSubscription => "plan" in r;

function Payments({ rows, onAction, run }: { rows: PaymentRow[]; onAction: () => void; run: (f: () => Promise<unknown>) => Promise<void> }) {
  if (rows.length === 0) return <p className="text-sm text-muted">Aucun règlement déclaré ou enregistré.</p>;
  return (
    <ul className="flex flex-col gap-2">
      {rows.map((p) => (
        <li key={p.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-md border border-line p-3 text-sm">
          <span className="font-bold">{formatXof(p.amount_xof)}</span>
          <span>{METHOD_LABELS[p.method] ?? p.method}</span>
          {p.external_reference && <span className="text-muted">Réf. {p.external_reference}</span>}
          <span className="font-semibold">{PAYMENT_LABELS[p.status]}</span>
          {p.proof_url && <a href={p.proof_url} target="_blank" rel="noopener noreferrer" className="underline">Justificatif</a>}
          {p.status === "declared" && (
            <span className="ml-auto flex gap-2">
              <button className="rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-white" onClick={() => run(async () => { await confirmPayment(p.id); onAction(); })}>
                Confirmer l&apos;encaissement
              </button>
              <button
                className="rounded-lg border border-line-strong px-3 py-1.5 text-xs font-bold"
                onClick={() => {
                  const note = window.prompt("Motif du rejet (conservé dans le journal d'audit) :");
                  if (note) void run(async () => { await rejectPayment(p.id, note); onAction(); });
                }}
              >
                Rejeter
              </button>
            </span>
          )}
        </li>
      ))}
    </ul>
  );
}

function RecordForm({ kind, id, onDone, run }: { kind: Kind; id: string; onDone: () => void; run: (f: () => Promise<unknown>) => Promise<void> }) {
  const [open, setOpen] = useState(false);
  if (!open) return <button className="self-start text-sm underline" onClick={() => setOpen(true)}>Enregistrer un encaissement reçu</button>;
  return (
    <form
      className="grid gap-2 rounded-md bg-sand p-3 sm:grid-cols-2"
      onSubmit={(e) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        if (!(form.get("proof") as File | null)?.size) form.delete("proof");
        void run(async () => { await recordPayment(kind, id, form); setOpen(false); onDone(); });
      }}
    >
      <label className="flex flex-col gap-1 text-xs font-bold">Moyen
        <select name="method" className="min-h-10 rounded-lg border border-line-strong bg-white px-2 font-normal">
          {Object.entries(METHOD_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </label>
      <label className="flex flex-col gap-1 text-xs font-bold">Montant reçu (FCFA)
        <input name="amount_xof" required inputMode="numeric" pattern="[0-9]+" className="min-h-10 rounded-lg border border-line-strong bg-white px-2 font-normal" />
      </label>
      <label className="flex flex-col gap-1 text-xs font-bold">Référence de l&apos;opération
        <input name="external_reference" maxLength={80} className="min-h-10 rounded-lg border border-line-strong bg-white px-2 font-normal" />
      </label>
      <label className="flex flex-col gap-1 text-xs font-bold">Justificatif (PDF, JPEG, PNG)
        <input name="proof" type="file" accept="application/pdf,image/jpeg,image/png" className="text-sm font-normal" />
      </label>
      <div className="flex gap-2 sm:col-span-2">
        <button type="submit" className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white">Enregistrer (encaissement confirmé)</button>
        <button type="button" className="px-3 text-sm underline" onClick={() => setOpen(false)}>Annuler</button>
      </div>
    </form>
  );
}

function RowCard({ kind, row, reload, run }: { kind: Kind; row: Row; reload: () => void; run: (f: () => Promise<unknown>) => Promise<void> }) {
  const sub = isSub(row);
  const title = sub ? `${row.plan.name} · ${formatXof(row.plan.price_xof)}` : `${row.product.code} · ${formatXof(row.product.price_xof)}`;
  const who = sub ? `${row.subscriber.name} (${row.subscriber.type === "agency" ? "agence" : "particulier"}), demandé par ${row.subscriber.requested_by}` : `${row.advertiser} · annonce ${row.listing.reference}`;
  const ask = (label: string, action: "suspend" | "cancel") => {
    const reason = window.prompt(`${label} : motif (conservé dans le journal d'audit) :`);
    if (reason) void run(async () => { await lifecycle(kind, row.id, action, reason); reload(); });
  };
  return (
    <li className="flex flex-col gap-3 rounded-lg border border-line bg-white p-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="text-lg font-extrabold">{title}</h2>
          <p className="text-sm text-muted">{who}</p>
          <p className="text-xs text-muted">Référence de paiement : <strong>{row.payment_reference}</strong></p>
        </div>
        <div className="flex flex-col items-end gap-1">
          <span className="rounded-md bg-sand px-2.5 py-1 text-xs font-bold">{STATUS_LABELS[row.status] ?? row.status}</span>
          <span className={`rounded-md px-2.5 py-1 text-xs font-bold ${SETTLEMENT_TONE[row.settlement]}`}>{SETTLEMENT_LABELS[row.settlement]}</span>
        </div>
      </div>
      {row.reason && <p className="text-sm">Motif : {row.reason}</p>}
      <Payments rows={row.payments} onAction={reload} run={run} />
      {row.status === "pending" && (sub || row.source !== "credit") && <RecordForm kind={kind} id={row.id} onDone={reload} run={run} />}
      <div className="flex flex-wrap gap-2">
        {canActivate(row.status, row.settlement) && (
          <button className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-hover" onClick={() => run(async () => { await lifecycle(kind, row.id, "activate"); reload(); })}>Activer</button>
        )}
        {row.status === "pending" && row.settlement !== "confirmed" && row.settlement !== "not_required" && (
          <span className="self-center text-xs text-muted">Activation possible seulement après un encaissement confirmé.</span>
        )}
        {row.status === "active" && <button className="rounded-lg border border-line-strong px-4 py-2 text-sm font-bold" onClick={() => ask("Suspendre", "suspend")}>Suspendre</button>}
        {row.status === "suspended" && kind === "subscriptions" && (
          <button className="rounded-lg border border-line-strong px-4 py-2 text-sm font-bold" onClick={() => run(async () => { await lifecycle(kind, row.id, "reinstate"); reload(); })}>Rétablir</button>
        )}
        {["pending", "active", "suspended"].includes(row.status) && <button className="rounded-lg border border-line-strong px-4 py-2 text-sm font-bold text-danger" onClick={() => ask("Annuler", "cancel")}>Annuler</button>}
      </div>
    </li>
  );
}

export default function SubscriptionsAdminView() {
  const [kind, setKind] = useState<Kind>("subscriptions");
  const [status, setStatus] = useState("pending");
  const [rows, setRows] = useState<Row[] | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [version, setVersion] = useState(0);
  const reload = () => setVersion((v) => v + 1);

  useEffect(() => {
    let cancelled = false;
    const load = kind === "subscriptions" ? fetchSubscriptions(status) : fetchPremium(status);
    load
      .then((r) => { if (!cancelled) { setRows(r.data as Row[]); setCursor(r.meta.next_cursor); setError(""); } })
      .catch((e) => !cancelled && setError(msg(e)));
    return () => { cancelled = true; };
  }, [kind, status, version]);

  async function run(action: () => Promise<unknown>) {
    setError("");
    try { await action(); } catch (e) { setError(msg(e)); }
  }

  async function more(next: string) {
    try {
      const r = await (kind === "subscriptions" ? fetchSubscriptions(status, next) : fetchPremium(status, next));
      setRows((prev) => [...(prev ?? []), ...(r.data as Row[])]);
      setCursor(r.meta.next_cursor);
    } catch (e) { setError(msg(e)); }
  }

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-extrabold">Abonnements et règlements</h1>
      <p className="text-sm text-muted">Un règlement déclaré n&apos;est pas un revenu : rien n&apos;est activé tant qu&apos;un encaissement n&apos;est pas confirmé ici.</p>
      <div className="flex flex-wrap gap-3">
        <div className="flex rounded-xl bg-sand p-1" role="tablist">
          {([["subscriptions", "Abonnements"], ["premium", "Mises en avant"]] as const).map(([k, label]) => (
            <button key={k} role="tab" aria-selected={kind === k} onClick={() => { setKind(k); setRows(null); }} className={`min-h-10 rounded-lg px-4 text-sm font-bold ${kind === k ? "bg-white text-primary shadow-card" : "text-muted"}`}>{label}</button>
          ))}
        </div>
        <select aria-label="Statut" className="min-h-10 rounded-lg border border-line-strong bg-white px-3 text-sm" value={status} onChange={(e) => { setStatus(e.target.value); setRows(null); }}>
          <option value="">Tous les statuts</option>
          {Object.entries(STATUS_LABELS).filter(([k]) => kind === "subscriptions" || k !== "replaced").map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </div>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {rows === null && !error && <p>Chargement…</p>}
      {rows?.length === 0 && <p>Rien à afficher pour ce filtre.</p>}
      <ul className="flex flex-col gap-4">
        {rows?.map((r) => <RowCard key={r.id} kind={kind} row={r} reload={reload} run={run} />)}
      </ul>
      {cursor && <button className="self-start underline" onClick={() => more(cursor)}>Voir plus</button>}
    </section>
  );
}
