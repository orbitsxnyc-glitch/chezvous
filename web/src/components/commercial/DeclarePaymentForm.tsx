"use client";

import { useState } from "react";
import type { FormEvent } from "react";
import { FormMessage } from "@/components/ui";
import { METHOD_LABELS } from "@/lib/commercial";
import { generalError } from "@/lib/forms";

/** Déclaration d'un règlement hors ligne : n'ouvre aucun droit tant qu'un administrateur ne l'a pas confirmé. */
export function DeclarePaymentForm({ defaultAmount, onSubmit }: { defaultAmount: number; onSubmit: (form: FormData) => Promise<void> }) {
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    if (!(form.get("proof") as File | null)?.size) form.delete("proof");
    setPending(true);
    setError("");
    try {
      await onSubmit(form);
      setSent(true);
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPending(false);
    }
  }

  if (sent) return <p role="status" className="text-sm">Règlement déclaré. Il sera pris en compte après confirmation par notre équipe.</p>;

  return (
    <form onSubmit={submit} className="flex flex-col gap-2 text-sm">
      <p className="font-medium">J&apos;ai effectué le règlement</p>
      <label className="flex flex-col gap-1">
        Moyen
        <select name="method" className="rounded border border-zinc-400 px-2 py-1">
          {Object.entries(METHOD_LABELS).map(([v, l]) => (
            <option key={v} value={v}>{l}</option>
          ))}
        </select>
      </label>
      <label className="flex flex-col gap-1">
        Montant (FCFA)
        <input name="amount_xof" type="number" min={0} required defaultValue={defaultAmount} className="rounded border border-zinc-400 px-2 py-1" />
      </label>
      <label className="flex flex-col gap-1">
        Référence de l&apos;opération
        <input name="external_reference" maxLength={80} className="rounded border border-zinc-400 px-2 py-1" />
      </label>
      <label className="flex flex-col gap-1">
        Justificatif (PDF, JPEG ou PNG, 5 Mo maximum)
        <input name="proof" type="file" accept="application/pdf,image/jpeg,image/png" />
      </label>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      <button type="submit" disabled={pending} className="self-start rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50">
        {pending ? "Envoi…" : "Déclarer mon règlement"}
      </button>
    </form>
  );
}
