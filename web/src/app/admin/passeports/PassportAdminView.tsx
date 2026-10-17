"use client";

import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { FormMessage } from "@/components/ui";
import { CHECK_TYPE_LABELS, fetchPassportAdmin, recordPassportCheck, type PassportAdminView as View } from "@/lib/admin";
import { ApiError } from "@/lib/api";
import { fieldErrors } from "@/lib/forms";

const msg = (e: unknown) => (e instanceof ApiError ? e.message : "Erreur.");
const STATUS: Record<string, string> = { passed: "Réussi", failed: "Échoué", pending: "En attente", expired: "Expiré" };
const date = (iso: string) => new Date(iso).toLocaleDateString("fr-FR");

export default function PassportAdminView() {
  const initial = useSearchParams().get("ref") ?? "";
  const [reference, setReference] = useState(initial);
  const [view, setView] = useState<View | null>(null);
  const [error, setError] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState("");
  const [pending, setPending] = useState(false);

  async function load(ref: string) {
    setError(""); setNotice(""); setView(null);
    try {
      setView((await fetchPassportAdmin(ref)).data);
    } catch (e) {
      setError(e instanceof ApiError && e.status === 404 ? "Aucune annonce avec cette référence." : msg(e));
    }
  }

  useEffect(() => {
    if (!initial) return;
    let cancelled = false;
    fetchPassportAdmin(initial)
      .then((r) => !cancelled && setView(r.data))
      .catch((e) => !cancelled && setError(e instanceof ApiError && e.status === 404 ? "Aucune annonce avec cette référence." : msg(e)));
    return () => { cancelled = true; };
  }, [initial]);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!view) return;
    const f = new FormData(event.currentTarget);
    setPending(true); setError(""); setFields({}); setNotice("");
    try {
      const validUntil = String(f.get("valid_until") ?? "");
      const res = await recordPassportCheck(view.listing.reference, {
        type: String(f.get("type")), status: String(f.get("status")) as "passed" | "failed", note: String(f.get("note") ?? "").trim() || undefined, valid_until: validUntil || undefined,
      });
      setView(res.data);
      setNotice("Contrôle enregistré. Il apparaît sur le Passeport public s'il est réussi.");
      event.currentTarget.reset();
    } catch (e) {
      setFields(fieldErrors(e));
      setError(msg(e));
    }
    setPending(false);
  }

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-extrabold">Contrôles du Passeport du Bien</h1>
      <p className="text-sm text-muted">Chaque contrôle est daté et conservé : en rejouer un ajoute une ligne, la plus récente fait foi. Un contrôle échoué retire l&apos;ancien succès du Passeport public.</p>
      <form className="flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); void load(reference); }}>
        <input aria-label="Référence de l'annonce" placeholder="CV-2026-000123" className="min-h-11 min-w-64 rounded-lg border border-line-strong bg-white px-3" value={reference} onChange={(e) => setReference(e.target.value)} />
        <button type="submit" className="min-h-11 rounded-lg bg-primary px-4 text-sm font-bold text-white hover:bg-primary-hover">Rechercher</button>
      </form>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {notice && <FormMessage kind="info">{notice}</FormMessage>}

      {view && (
        <div className="flex flex-col gap-4">
          <div className="rounded-lg border border-line bg-white p-4">
            <p className="text-xs text-muted">{view.listing.reference} · {view.listing.status}</p>
            <h2 className="text-lg font-extrabold">{view.listing.title ?? "Sans titre"}</h2>
          </div>

          <form onSubmit={onSubmit} className="grid gap-3 rounded-lg border border-line bg-white p-4 sm:grid-cols-2">
            <h3 className="font-extrabold sm:col-span-2">Saisir un contrôle</h3>
            <label className="flex flex-col gap-1 text-sm font-bold">Contrôle
              <select name="type" className="min-h-11 rounded-lg border border-line-strong bg-white px-3 font-normal">
                {view.manual_types.map((t) => <option key={t} value={t}>{CHECK_TYPE_LABELS[t] ?? t}</option>)}
              </select>
            </label>
            <label className="flex flex-col gap-1 text-sm font-bold">Résultat
              <select name="status" className="min-h-11 rounded-lg border border-line-strong bg-white px-3 font-normal">
                <option value="passed">Réussi</option>
                <option value="failed">Échoué</option>
              </select>
            </label>
            <label className="flex flex-col gap-1 text-sm font-bold sm:col-span-2">Note interne (obligatoire en cas d&apos;échec, jamais publique)
              <textarea name="note" rows={3} maxLength={1000} className="rounded-lg border border-line-strong bg-white px-3 py-2 font-normal" />
              {fields.note && <span role="alert" className="font-normal text-danger">{fields.note}</span>}
            </label>
            <label className="flex flex-col gap-1 text-sm font-bold">Valable jusqu&apos;au (facultatif)
              <input name="valid_until" type="date" className="min-h-11 rounded-lg border border-line-strong bg-white px-3 font-normal" />
              {fields.valid_until && <span role="alert" className="font-normal text-danger">{fields.valid_until}</span>}
            </label>
            <div className="flex items-end">
              <button type="submit" disabled={pending} className="min-h-11 rounded-lg bg-primary px-5 text-sm font-bold text-white hover:bg-primary-hover disabled:opacity-50">Enregistrer le contrôle</button>
            </div>
            <p className="text-xs text-muted sm:col-span-2">Le téléphone vient de l&apos;inscription et le contrôle professionnel de la vérification d&apos;agence : ils ne se saisissent pas ici. Un contrôle de document ne garantit pas la propriété juridique.</p>
          </form>

          <div className="rounded-lg border border-line bg-white p-4">
            <h3 className="font-extrabold">Historique</h3>
            {view.history.length === 0 ? <p className="mt-2 text-sm text-muted">Aucun contrôle saisi.</p> : (
              <ul className="mt-3 flex flex-col gap-2">
                {view.history.map((h, i) => (
                  <li key={i} className="rounded-md border border-line p-3 text-sm">
                    <strong>{CHECK_TYPE_LABELS[h.type] ?? h.type}</strong> · <span className={h.status === "passed" ? "text-success" : "text-danger"}>{STATUS[h.status] ?? h.status}</span>
                    <span className="text-muted"> · le {date(h.performed_at)}{h.by ? ` par ${h.by}` : ""}{h.valid_until ? ` · valable jusqu'au ${date(h.valid_until)}` : ""}</span>
                    {h.note && <p className="mt-1 text-muted">Note : {h.note}</p>}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </section>
  );
}
