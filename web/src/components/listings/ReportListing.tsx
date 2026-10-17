"use client";

import Link from "next/link";
import { useState } from "react";
import type { FormEvent } from "react";
import { useAuth } from "@/components/AuthProvider";
import { FormMessage, SubmitButton } from "@/components/ui";
import { apiFetch } from "@/lib/api";
import { generalError } from "@/lib/forms";

type Reason = { code: string; label: string };

/** Signalement d'une annonce (fraude, bien indisponible, informations erronées…). Réservé aux comptes connectés ; traité par la modération. */
export function ReportListing({ listingId }: { listingId: string }) {
  const { user, loading } = useAuth();
  const [open, setOpen] = useState(false);
  const [reasons, setReasons] = useState<Reason[]>([]);
  const [error, setError] = useState("");
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);

  async function toggle() {
    const next = !open;
    setOpen(next);
    if (next && reasons.length === 0) {
      try {
        setReasons((await apiFetch<{ data: Reason[] }>("/report-reasons")).data);
      } catch {
        setError("Impossible de charger les motifs. Réessayez dans un instant.");
      }
    }
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    setPending(true);
    setError("");
    try {
      await apiFetch(`/listings/${encodeURIComponent(listingId)}/reports`, {
        method: "POST",
        body: { reason_code: String(f.get("reason")), comment: String(f.get("comment") ?? "").trim() || undefined },
      });
      setDone(true);
    } catch (e) {
      setError(generalError(e) || "Vérifiez les informations saisies.");
    }
    setPending(false);
  }

  if (loading) return null;
  if (!user) {
    return <p className="text-center text-xs text-muted"><Link href="/connexion" className="underline">Connectez-vous</Link> pour signaler cette annonce.</p>;
  }
  if (done) return <FormMessage kind="info">Merci : votre signalement a été transmis à notre équipe.</FormMessage>;

  return (
    <div className="flex flex-col gap-3">
      <button type="button" onClick={toggle} aria-expanded={open} className="self-center text-xs font-semibold text-muted underline">
        Signaler cette annonce
      </button>
      {open && (
        <form onSubmit={onSubmit} className="flex flex-col gap-3 rounded-xl border border-line bg-white p-4">
          <label className="flex flex-col gap-1.5 text-xs font-bold">
            Motif
            <select name="reason" required className="min-h-11 rounded-lg border border-line-strong bg-white px-3 text-sm font-normal">
              <option value="">Choisir…</option>
              {reasons.map((r) => <option key={r.code} value={r.code}>{r.label}</option>)}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-bold">
            Précisions (facultatif)
            <textarea name="comment" rows={3} maxLength={1000} className="rounded-lg border border-line-strong bg-white px-3 py-2 text-sm font-normal" />
          </label>
          {error && <FormMessage kind="error">{error}</FormMessage>}
          <SubmitButton pending={pending}>Envoyer le signalement</SubmitButton>
        </form>
      )}
    </div>
  );
}
