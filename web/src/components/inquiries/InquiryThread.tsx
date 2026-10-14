"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useState } from "react";
import type { FormEvent } from "react";
import { FormMessage, Page } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import {
  addNote, fetchInquiry, formatDateTime, sendMessage, setInquiryStatus, STATUS_LABELS,
  type InquiryDetail,
} from "@/lib/engagement";
import { generalError } from "@/lib/forms";
import { VisitPanel } from "./VisitPanel";

export default function InquiryThread({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const { user, loading } = useRequireAuth();
  const [inquiry, setInquiry] = useState<InquiryDetail | null>(null);
  const [error, setError] = useState("");
  const [version, setVersion] = useState(0);
  const refresh = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchInquiry(id)
      .then((r) => !cancelled && setInquiry(r.data))
      .catch((e) => !cancelled && setError(generalError(e) || "Conversation introuvable."));
    return () => {
      cancelled = true;
    };
  }, [user, id, version]);

  async function run(fn: () => Promise<unknown>) {
    setError("");
    try {
      await fn();
      refresh();
    } catch (e) {
      setError(generalError(e));
    }
  }

  async function onMessage(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = String(new FormData(form).get("body")).trim();
    if (!body) return;
    await run(() => sendMessage(id, body));
    form.reset();
  }

  async function onNote(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = String(new FormData(form).get("note")).trim();
    if (!body) return;
    await run(() => addNote(id, body));
    form.reset();
  }

  if (loading || !inquiry) return <Page title="Conversation">{error ? <p role="alert">{error}</p> : <p>Chargement…</p>}</Page>;

  const isOwnerSide = inquiry.role === "owner";
  const open = !["concluded", "lost", "closed"].includes(inquiry.status);
  const canWrite = !isOwnerSide || inquiry.can_respond;

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold">{inquiry.listing.title ?? inquiry.listing.reference}</h1>
        <p className="text-sm">
          {STATUS_LABELS[inquiry.status]} · <Link className="underline" href={`/annonces/${inquiry.listing.reference}`}>Voir l&apos;annonce</Link>
        </p>
        {isOwnerSide && inquiry.requester && (
          <p className="text-sm">
            Demandeur : {inquiry.requester.name} · {inquiry.requester.phone} (contact préféré : {inquiry.preferred_channel})
          </p>
        )}
        {!isOwnerSide && inquiry.advertiser && <p className="text-sm">Annonceur : {inquiry.advertiser.name}</p>}
      </header>

      {error && <FormMessage kind="error">{error}</FormMessage>}

      <section aria-labelledby="messages" className="flex flex-col gap-2">
        <h2 id="messages" className="text-lg font-semibold">Messages</h2>
        <ul className="flex flex-col gap-2">
          {inquiry.messages.map((m) => (
            <li key={m.id} className={`max-w-[85%] rounded p-3 ${m.mine ? "self-end bg-zinc-200" : "self-start border border-zinc-300"}`}>
              <p className="whitespace-pre-line">{m.body}</p>
              <p className="text-xs text-zinc-600">
                {m.mine ? "Vous" : m.sender_name ?? (m.from === "owner" ? "Annonceur" : "Demandeur")} · {new Date(m.created_at).toLocaleString("fr-FR")}
              </p>
            </li>
          ))}
        </ul>
        {open && canWrite ? (
          <form onSubmit={onMessage} className="flex flex-col gap-2">
            <label className="flex flex-col gap-1 text-sm">
              Votre message
              <textarea name="body" required maxLength={2000} rows={3} className="rounded border border-zinc-400 px-3 py-2" />
            </label>
            <button type="submit" className="self-start rounded bg-zinc-900 px-4 py-2 text-white">Envoyer</button>
          </form>
        ) : open ? (
          <p className="text-sm">Cette demande est attribuée à un autre membre de l&apos;agence : vous pouvez la lire sans y répondre.</p>
        ) : (
          <p className="text-sm">Cette demande est terminée.</p>
        )}
      </section>

      <VisitPanel inquiry={inquiry} isOwnerSide={isOwnerSide} canAct={Boolean(canWrite)} open={open} onChange={refresh} />

      {open && (
        <section aria-labelledby="statut" className="flex flex-wrap gap-3">
          <h2 id="statut" className="sr-only">Statut de la demande</h2>
          {isOwnerSide && inquiry.can_respond ? (
            <>
              <button type="button" className="underline" onClick={() => run(() => setInquiryStatus(id, "concluded"))}>Marquer conclue</button>
              <button type="button" className="underline" onClick={() => run(() => setInquiryStatus(id, "lost"))}>Marquer perdue</button>
            </>
          ) : null}
          {!isOwnerSide && (
            <button type="button" className="underline" onClick={() => run(() => setInquiryStatus(id, "closed"))}>Fermer ma demande</button>
          )}
        </section>
      )}

      {isOwnerSide && inquiry.notes && (
        <section aria-labelledby="notes" className="flex flex-col gap-2 rounded border border-dashed border-zinc-400 p-3">
          <h2 id="notes" className="text-lg font-semibold">Notes internes</h2>
          <p className="text-xs text-zinc-600">Visibles de l&apos;agence uniquement, jamais du demandeur.</p>
          <ul className="flex flex-col gap-1 text-sm">
            {inquiry.notes.map((n) => (
              <li key={n.id}>
                {n.body} <span className="text-xs text-zinc-600">({n.author}, {formatDateTime(n.created_at)})</span>
              </li>
            ))}
          </ul>
          {inquiry.can_respond && (
            <form onSubmit={onNote} className="flex gap-2">
              <input name="note" aria-label="Nouvelle note" maxLength={2000} className="flex-1 rounded border border-zinc-400 px-2 py-1" />
              <button type="submit" className="rounded border border-zinc-400 px-3">Ajouter</button>
            </form>
          )}
        </section>
      )}
    </main>
  );
}
