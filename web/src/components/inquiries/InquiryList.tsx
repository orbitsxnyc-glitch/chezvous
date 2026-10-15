"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Page } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { fetchInbox, fetchSent, STATUS_LABELS, type InquirySummary } from "@/lib/engagement";
import { generalError } from "@/lib/forms";

/** Liste des demandes envoyées (chercheur) ou reçues (annonceur). L'API ne renvoie que ce que l'utilisateur a le droit de voir. */
export default function InquiryList({ mode }: { mode: "sent" | "inbox" }) {
  const { user, loading } = useRequireAuth();
  const [items, setItems] = useState<InquirySummary[] | null>(null);
  const [next, setNext] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [cursor, setCursor] = useState<string | undefined>(undefined);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    (mode === "sent" ? fetchSent(cursor) : fetchInbox(cursor))
      .then((r) => {
        if (cancelled) return;
        setItems((prev) => (cursor && prev ? [...prev, ...r.data] : r.data));
        setNext(r.meta?.next_cursor ?? null);
      })
      .catch((e) => !cancelled && setError(generalError(e)));
    return () => {
      cancelled = true;
    };
  }, [user, mode, cursor]);

  const title = mode === "sent" ? "Mes demandes" : "Demandes reçues";
  if (loading || items === null) return <Page title={title}>{error ? <p role="alert">{error}</p> : <p>Chargement…</p>}</Page>;

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">{title}</h1>
      {items.length === 0 ? (
        <p>{mode === "sent" ? "Vous n'avez envoyé aucune demande." : "Aucune demande reçue pour le moment."}</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {items.map((i) => (
            <li key={i.id}>
              <Link href={`/demandes/${i.id}`} className="flex flex-col gap-1 rounded-lg border border-line p-3">
                <span className="font-medium">{i.listing.title ?? i.listing.reference}</span>
                <span className="text-sm">
                  {STATUS_LABELS[i.status]}
                  {i.unread > 0 ? ` · ${i.unread} non lu(s)` : ""}
                  {i.requester ? ` · ${i.requester.name}` : ""}
                </span>
                <span className="text-xs text-muted">Dernière activité : {new Date(i.last_activity_at).toLocaleString("fr-FR")}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
      {next && (
        <button type="button" onClick={() => setCursor(next)} className="self-start underline">
          Voir plus
        </button>
      )}
      {error && <p role="alert">{error}</p>}
    </main>
  );
}
