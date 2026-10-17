"use client";

import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import {
  approveVerification, DOCUMENT_LABELS, fetchVerification, fetchVerifications, rejectVerification,
  type VerificationDetail, type VerificationSummary,
} from "@/lib/admin";
import { ApiError } from "@/lib/api";

const msg = (e: unknown) => (e instanceof ApiError ? e.message : "Erreur.");
const size = (b: number) => (b > 1_000_000 ? `${(b / 1_000_000).toFixed(1)} Mo` : `${Math.max(1, Math.round(b / 1000))} Ko`);

function Detail({ id, onDone, onError }: { id: string; onDone: (id: string) => void; onError: (m: string) => void }) {
  const [detail, setDetail] = useState<VerificationDetail | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    // Les liens de justificatifs sont signés et de courte durée : on les demande à l'ouverture, jamais en avance.
    fetchVerification(id).then((r) => !cancelled && setDetail(r.data)).catch((e) => !cancelled && onError(msg(e)));
    return () => { cancelled = true; };
  }, [id, onError]);

  async function decide(action: () => Promise<unknown>) {
    setBusy(true);
    try { await action(); onDone(id); } catch (e) { onError(msg(e)); setBusy(false); }
  }

  if (!detail) return <p className="text-sm">Chargement du dossier…</p>;
  return (
    <div className="flex flex-col gap-3 rounded-md bg-sand p-4">
      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt className="font-bold">NINEA</dt><dd>{detail.agency.ninea ?? "non renseigné"}</dd>
        <dt className="font-bold">RCCM</dt><dd>{detail.agency.rccm ?? "non renseigné"}</dd>
      </dl>
      <ul className="flex flex-col gap-1">
        {detail.documents.map((d) => (
          <li key={d.url} className="text-sm">
            <a href={d.url} target="_blank" rel="noopener noreferrer" className="font-bold underline">{DOCUMENT_LABELS[d.kind] ?? d.kind}</a>{" "}
            <span className="text-muted">({d.mime}, {size(d.size_bytes)}) · lien valable {d.expires_in_minutes} min</span>
          </li>
        ))}
        {detail.documents.length === 0 && <li className="text-sm text-danger">Aucun document joint.</li>}
      </ul>
      <p className="text-xs text-muted">Documents privés : ne les copiez ni ne les partagez. Un contrôle de document ne garantit pas la propriété juridique.</p>
      <div className="flex flex-wrap gap-2">
        <button disabled={busy} className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-hover disabled:opacity-50" onClick={() => decide(() => approveVerification(id))}>
          Approuver (agence vérifiée)
        </button>
        <button
          disabled={busy}
          className="rounded-lg border border-line-strong px-4 py-2 text-sm font-bold text-danger disabled:opacity-50"
          onClick={() => {
            const note = window.prompt("Motif du refus, transmis à l'agence :");
            if (note) void decide(() => rejectVerification(id, note));
          }}
        >
          Refuser
        </button>
      </div>
    </div>
  );
}

export default function VerificationsView() {
  const [items, setItems] = useState<VerificationSummary[] | null>(null);
  const [open, setOpen] = useState<string | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;
    fetchVerifications().then((r) => !cancelled && setItems(r.data)).catch((e) => !cancelled && setError(msg(e)));
    return () => { cancelled = true; };
  }, []);

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-extrabold">Vérification des agences</h1>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {items === null && !error && <p>Chargement…</p>}
      {items?.length === 0 && <p>Aucun dossier en attente.</p>}
      <ul className="flex flex-col gap-3">
        {items?.map((v) => (
          <li key={v.id} className="flex flex-col gap-3 rounded-lg border border-line bg-white p-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h2 className="font-extrabold">{v.agency}</h2>
                <p className="text-xs text-muted">Déposé le {new Date(v.submitted_at).toLocaleDateString("fr-FR")}</p>
              </div>
              <button className="rounded-lg border border-line-strong px-3 py-2 text-sm font-bold" onClick={() => setOpen(open === v.id ? null : v.id)}>
                {open === v.id ? "Fermer" : "Examiner le dossier"}
              </button>
            </div>
            {open === v.id && (
              <Detail id={v.id} onError={setError} onDone={(id) => { setItems((prev) => (prev ?? []).filter((x) => x.id !== id)); setOpen(null); }} />
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}
