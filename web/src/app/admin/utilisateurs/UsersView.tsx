"use client";

import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import { fetchUsers, reactivateUser, suspendUser, type AdminUser } from "@/lib/admin";
import { ApiError } from "@/lib/api";

export default function UsersView() {
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [items, setItems] = useState<AdminUser[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;
    fetchUsers(q, status)
      .then((r) => { if (!cancelled) { setItems(r.data); setCursor(r.meta.next_cursor); setError(""); } })
      .catch((e) => !cancelled && setError(e instanceof ApiError ? e.message : "Erreur."));
    return () => { cancelled = true; };
  }, [q, status]);

  async function more(next: string) {
    try {
      const r = await fetchUsers(q, status, next);
      setItems((prev) => [...prev, ...r.data]);
      setCursor(r.meta.next_cursor);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur.");
    }
  }

  const replace = (u: AdminUser) => setItems((prev) => prev.map((x) => (x.id === u.id ? u : x)));

  async function suspend(u: AdminUser) {
    const reason = window.prompt(`Motif de suspension de ${u.name} (conservé dans le journal d'audit) :`);
    if (!reason) return;
    try { replace((await suspendUser(u.id, reason)).data); } catch (e) { setError(e instanceof ApiError ? e.message : "Erreur."); }
  }
  async function reactivate(u: AdminUser) {
    try { replace((await reactivateUser(u.id)).data); } catch (e) { setError(e instanceof ApiError ? e.message : "Erreur."); }
  }

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-semibold">Utilisateurs</h1>
      <div className="flex flex-wrap gap-2">
        <input aria-label="Rechercher" placeholder="Nom, e-mail, téléphone, identifiant" className="min-w-64 rounded-lg border border-line-strong px-3 py-2" value={q} onChange={(e) => setQ(e.target.value)} />
        <select aria-label="Statut" className="rounded-lg border border-line-strong px-3 py-2" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Tous les statuts</option>
          <option value="active">Actifs</option>
          <option value="suspended">Suspendus</option>
        </select>
      </div>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      <table className="w-full text-left text-sm">
        <thead><tr><th>Nom</th><th>Contact</th><th>Type / rôles</th><th>Statut</th><th><span className="sr-only">Actions</span></th></tr></thead>
        <tbody>
          {items.map((u) => (
            <tr key={u.id} className="border-t border-line align-top">
              <td className="py-2">{u.name}{u.agency && <span className="block text-xs text-muted">{u.agency.name} ({u.agency.role})</span>}</td>
              <td>{u.phone}{u.email && <span className="block text-xs">{u.email}</span>}</td>
              <td>{u.account_type}{u.roles.length > 0 && <span className="block text-xs">{u.roles.join(", ")}</span>}</td>
              <td>{u.status}</td>
              <td>
                {u.status === "suspended"
                  ? <button className="underline" onClick={() => reactivate(u)}>Réactiver</button>
                  : <button className="underline" onClick={() => suspend(u)}>Suspendre</button>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {items.length === 0 && !error && <p>Aucun résultat.</p>}
      {cursor && <button className="self-start underline" onClick={() => more(cursor)}>Voir plus</button>}
    </section>
  );
}
