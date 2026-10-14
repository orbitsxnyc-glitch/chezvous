"use client";

import { useState } from "react";
import type { FormEvent } from "react";
import { FormMessage } from "@/components/ui";
import { formatDateTime, localToIso, proposeVisit, visitAction, visitFeedback, type InquiryDetail, type Visit } from "@/lib/engagement";
import { generalError } from "@/lib/forms";

const STATUS: Record<Visit["status"], string> = {
  proposed: "Proposée", confirmed: "Confirmée", rescheduled: "À reprogrammer", cancelled: "Annulée", done: "Effectuée",
};

/** Saisie de 1 à 3 créneaux (heure locale du navigateur, envoyée en UTC). */
function SlotInputs({ onSubmit, label }: { onSubmit: (slots: string[]) => Promise<void>; label: string }) {
  const [pending, setPending] = useState(false);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const slots = ["s1", "s2", "s3"].map((k) => String(form.get(k) ?? "")).filter(Boolean).map(localToIso);
    setPending(true);
    await onSubmit(slots);
    setPending(false);
  }
  return (
    <form onSubmit={submit} className="flex flex-col gap-2">
      {["s1", "s2", "s3"].map((k, i) => (
        <label key={k} className="flex flex-col gap-1 text-sm">
          Créneau {i + 1}{i === 0 ? "" : " (facultatif)"}
          <input type="datetime-local" name={k} required={i === 0} className="rounded border border-zinc-400 px-2 py-1" />
        </label>
      ))}
      <button type="submit" disabled={pending} className="self-start rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50">{label}</button>
    </form>
  );
}

export function VisitPanel({ inquiry, isOwnerSide, canAct, open, onChange }: { inquiry: InquiryDetail; isOwnerSide: boolean; canAct: boolean; open: boolean; onChange: () => void }) {
  const [error, setError] = useState("");
  const [chosen, setChosen] = useState("");
  const active = inquiry.visits.find((v) => ["proposed", "confirmed", "rescheduled"].includes(v.status));
  const done = inquiry.visits.find((v) => v.status === "done");

  async function run(fn: () => Promise<unknown>) {
    setError("");
    try {
      await fn();
      onChange();
    } catch (e) {
      setError(generalError(e));
    }
  }

  async function feedback(event: FormEvent<HTMLFormElement>, visit: Visit) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    await run(() => visitFeedback(visit.id, { available: form.get("available") === "yes", conform: form.get("conform") === "yes", comment: String(form.get("comment") ?? "").trim() || undefined }));
  }

  return (
    <section aria-labelledby="visite" className="flex flex-col gap-3 rounded border border-zinc-300 p-4">
      <h2 id="visite" className="text-lg font-semibold">Visite</h2>
      {error && <FormMessage kind="error">{error}</FormMessage>}

      {active ? (
        <div className="flex flex-col gap-2">
          <p>
            {STATUS[active.status]}
            {active.scheduled_at ? ` : ${formatDateTime(active.scheduled_at)}` : ""}
          </p>
          {active.status !== "confirmed" && (
            <ul className="text-sm">
              {active.proposed_slots.map((s) => (
                <li key={s}>
                  {active.awaiting_me && canAct ? (
                    <label className="flex items-center gap-2">
                      <input type="radio" name="slot" value={s} checked={chosen === s} onChange={() => setChosen(s)} /> {formatDateTime(s)}
                    </label>
                  ) : (
                    formatDateTime(s)
                  )}
                </li>
              ))}
            </ul>
          )}
          {active.awaiting_me && canAct && (
            <div className="flex flex-col gap-2">
              <button type="button" disabled={!chosen} onClick={() => run(() => visitAction(active.id, { action: "confirm", slot: chosen }))} className="self-start rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50">
                Confirmer le créneau choisi
              </button>
              <details>
                <summary className="cursor-pointer text-sm underline">Proposer d&apos;autres créneaux</summary>
                <SlotInputs label="Envoyer ces créneaux" onSubmit={(slots) => run(() => visitAction(active.id, { action: "reschedule", slots }))} />
              </details>
            </div>
          )}
          {!active.awaiting_me && active.status !== "confirmed" && <p className="text-sm text-zinc-600">En attente de la réponse de l&apos;autre partie.</p>}
          {active.status === "confirmed" && canAct && (
            <details>
              <summary className="cursor-pointer text-sm underline">Reporter la visite</summary>
              <SlotInputs label="Proposer de nouveaux créneaux" onSubmit={(slots) => run(() => visitAction(active.id, { action: "reschedule", slots }))} />
            </details>
          )}
          {canAct && (
            <div className="flex gap-4 text-sm">
              <button type="button" className="underline" onClick={() => run(() => visitAction(active.id, { action: "cancel" }))}>Annuler la visite</button>
              {isOwnerSide && active.status === "confirmed" && active.scheduled_at && new Date(active.scheduled_at) <= new Date() && (
                <button type="button" className="underline" onClick={() => run(() => visitAction(active.id, { action: "done" }))}>Marquer comme effectuée</button>
              )}
            </div>
          )}
        </div>
      ) : open && canAct ? (
        <SlotInputs label="Proposer ces créneaux" onSubmit={async (slots) => { await run(() => proposeVisit(inquiry.id, slots)); }} />
      ) : (
        <p className="text-sm">Aucune visite en cours.</p>
      )}

      {done && !isOwnerSide && !done.feedback && (
        <form onSubmit={(e) => feedback(e, done)} className="flex flex-col gap-2 border-t border-zinc-300 pt-3">
          <p className="font-medium">Votre retour sur la visite</p>
          <fieldset className="flex gap-4 text-sm">
            <legend className="sr-only">Le bien est-il toujours disponible ?</legend>
            Disponible ? <label><input type="radio" name="available" value="yes" defaultChecked /> Oui</label> <label><input type="radio" name="available" value="no" /> Non</label>
          </fieldset>
          <fieldset className="flex gap-4 text-sm">
            <legend className="sr-only">Le bien est-il conforme à l&apos;annonce ?</legend>
            Conforme à l&apos;annonce ? <label><input type="radio" name="conform" value="yes" defaultChecked /> Oui</label> <label><input type="radio" name="conform" value="no" /> Non</label>
          </fieldset>
          <input name="comment" aria-label="Commentaire" maxLength={1000} placeholder="Commentaire (facultatif)" className="rounded border border-zinc-400 px-2 py-1" />
          <button type="submit" className="self-start rounded bg-zinc-900 px-4 py-2 text-white">Envoyer mon retour</button>
        </form>
      )}
      {done?.feedback && (
        <p className="text-sm">
          Retour du demandeur : {done.feedback.available ? "disponible" : "plus disponible"}, {done.feedback.conform ? "conforme" : "non conforme"}
          {done.feedback.comment ? ` — ${done.feedback.comment}` : ""}
        </p>
      )}
    </section>
  );
}
