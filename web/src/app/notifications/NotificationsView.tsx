"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Page } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { describeNotification, fetchNotifications, markNotificationsRead, notificationHref, type AppNotification } from "@/lib/engagement";
import { generalError } from "@/lib/forms";

export default function NotificationsView() {
  const { user, loading } = useRequireAuth();
  const [items, setItems] = useState<AppNotification[] | null>(null);
  const [unread, setUnread] = useState(0);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchNotifications()
      .then((r) => {
        if (cancelled) return;
        setItems(r.data);
        setUnread(r.meta.unread);
      })
      .catch((e) => !cancelled && setError(generalError(e)));
    return () => {
      cancelled = true;
    };
  }, [user]);

  async function readAll() {
    try {
      await markNotificationsRead();
      setItems((prev) => prev?.map((n) => ({ ...n, read: true })) ?? null);
      setUnread(0);
    } catch (e) {
      setError(generalError(e));
    }
  }

  if (loading || items === null) return <Page title="Notifications">{error ? <p role="alert">{error}</p> : <p>Chargement…</p>}</Page>;

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">Notifications{unread > 0 ? ` (${unread} non lue${unread > 1 ? "s" : ""})` : ""}</h1>
      {unread > 0 && <button type="button" onClick={readAll} className="self-start underline">Tout marquer comme lu</button>}
      {error && <p role="alert">{error}</p>}
      {items.length === 0 ? (
        <p>Aucune notification.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {items.map((n) => {
            const href = notificationHref(n);
            const text = describeNotification(n);
            return (
              <li key={n.id} className={`rounded border p-3 ${n.read ? "border-zinc-200 text-zinc-600" : "border-zinc-400 font-medium"}`}>
                {href ? <Link href={href} className="underline">{text}</Link> : text}
                <span className="block text-xs">{new Date(n.created_at).toLocaleString("fr-FR")}</span>
              </li>
            );
          })}
        </ul>
      )}
    </main>
  );
}
