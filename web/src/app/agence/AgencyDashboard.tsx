"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import type { FormEvent } from "react";
import { useAuth } from "@/components/AuthProvider";
import { Field, FormMessage, Page, SubmitButton } from "@/components/ui";
import {
  fetchAgency, fetchInvitations, fetchMembers, fetchStats, inviteMember, revokeInvitation, updateMember,
  type Agency, type AgencyStats, type Invitation, type Member,
} from "@/lib/agency";
import { ApiError } from "@/lib/api";
import { generalError } from "@/lib/forms";

/** Espace agence sobre (design Figma à venir). Les droits sont vérifiés par l'API, pas seulement ici. */
export default function AgencyDashboard() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const [agency, setAgency] = useState<Agency | null>(null);
  const [stats, setStats] = useState<AgencyStats | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [error, setError] = useState("");
  const [forbidden, setForbidden] = useState(false);
  const [pending, setPending] = useState(false);

  const isManager = agency?.me?.role === "manager";

  // Rechargement par compteur : l'effet relit l'API à chaque changement (au montage et après chaque action).
  const [version, setVersion] = useState(0);
  const refresh = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/connexion");
      return;
    }
    let cancelled = false;
    fetchAgency()
      .then(async ({ data: a }) => {
        const [s, m] = await Promise.all([fetchStats(), fetchMembers()]);
        const inv = a.me?.role === "manager" ? (await fetchInvitations()).data : [];
        if (cancelled) return;
        setAgency(a);
        setStats(s.data);
        setMembers(m.data);
        setInvitations(inv);
      })
      .catch((e) => {
        if (cancelled) return;
        if (e instanceof ApiError && e.status === 403) setForbidden(true);
        else setError(generalError(e));
      });
    return () => {
      cancelled = true;
    };
  }, [loading, user, router, version]);

  async function onInvite(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPending(true);
    setError("");
    try {
      await inviteMember({
        phone: String(form.get("phone") ?? "").trim() || undefined,
        email: String(form.get("email") ?? "").trim() || undefined,
        can_see_all: form.get("can_see_all") === "on",
      });
      event.currentTarget.reset();
      refresh();
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPending(false);
    }
  }

  async function act(fn: () => Promise<unknown>) {
    setError("");
    try {
      await fn();
      refresh();
    } catch (e) {
      setError(generalError(e));
    }
  }

  if (loading || (!agency && !forbidden && !error)) return <Page title="Espace agence"><p>Chargement…</p></Page>;
  if (forbidden) return <Page title="Espace agence"><p>Ce compte n&apos;appartient à aucune agence active.</p></Page>;

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
      <h1 className="text-2xl font-semibold">{agency?.name}</h1>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {agency && (
        <p className="text-sm">
          {agency.verified ? "✓ Agence vérifiée" : agency.verification?.status === "pending" ? "Vérification en cours d'examen" : "Agence non vérifiée"} ·{" "}
          <Link className="underline" href={`/agences/${agency.slug}`}>Voir le profil public</Link>
        </p>
      )}

      {stats && (
        <section aria-labelledby="stats" className="flex flex-col gap-2">
          <h2 id="stats" className="text-lg font-semibold">{stats.scope === "agency" ? "Activité de l'agence" : "Votre activité"}</h2>
          <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {[
              ["Annonces actives", stats.totals.active_listings],
              ["En modération", stats.totals.pending_listings],
              ["Vues", stats.totals.views],
              ["Contacts", stats.totals.contacts],
            ].map(([label, value]) => (
              <div key={label as string} className="rounded border border-zinc-300 p-3">
                <dt className="text-xs text-zinc-600">{label}</dt>
                <dd className="text-xl">{value}</dd>
              </div>
            ))}
          </dl>
          {stats.quota && <p className="text-sm">Quota : {stats.quota.used} / {stats.quota.limit} annonces actives</p>}
          <p className="text-xs text-zinc-600">{stats.note}</p>
        </section>
      )}

      <section aria-labelledby="equipe" className="flex flex-col gap-2">
        <h2 id="equipe" className="text-lg font-semibold">Équipe</h2>
        <ul className="flex flex-col gap-2">
          {members.map((m) => (
            <li key={m.id} className="flex flex-wrap items-center gap-3 rounded border border-zinc-300 p-3">
              <span className="font-medium">{m.name}</span>
              <span className="text-sm">{m.role === "manager" ? "Gérant" : "Agent"}</span>
              {m.phone && <span className="text-sm">{m.phone}</span>}
              {m.status === "suspended" && <span className="text-sm text-red-700">Suspendu</span>}
              {isManager && m.role === "agent" && (
                <>
                  <button type="button" className="text-sm underline" onClick={() => act(() => updateMember(m.id, { status: m.status === "suspended" ? "active" : "suspended" }))}>
                    {m.status === "suspended" ? "Réintégrer" : "Suspendre"}
                  </button>
                  <label className="flex items-center gap-1 text-sm">
                    <input type="checkbox" checked={Boolean(m.can_see_all)} onChange={(e) => act(() => updateMember(m.id, { can_see_all: e.target.checked }))} />
                    Voit toute l&apos;agence
                  </label>
                </>
              )}
            </li>
          ))}
        </ul>
      </section>

      {isManager && (
        <section aria-labelledby="invitations" className="flex flex-col gap-3">
          <h2 id="invitations" className="text-lg font-semibold">Inviter un agent</h2>
          <form onSubmit={onInvite} className="flex flex-col gap-3" noValidate>
            <Field label="Téléphone" name="phone" type="tel" placeholder="77 123 45 67" />
            <Field label="ou e-mail" name="email" type="email" />
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" name="can_see_all" /> Peut voir toutes les annonces de l&apos;agence
            </label>
            <SubmitButton pending={pending}>Envoyer l&apos;invitation</SubmitButton>
          </form>
          <ul className="flex flex-col gap-1 text-sm">
            {invitations.map((i) => (
              <li key={i.id} className="flex gap-3">
                <span>{i.phone ?? i.email}</span>
                <span>{i.status}</span>
                {i.status === "pending" && (
                  <button type="button" className="underline" onClick={() => act(() => revokeInvitation(i.id))}>Révoquer</button>
                )}
              </li>
            ))}
          </ul>
        </section>
      )}
    </main>
  );
}
