"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import type { ReactNode } from "react";
import { Field, FormMessage, SubmitButton } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import {
  isAdmin, twoFactorConfirm, twoFactorSetup, twoFactorStatus, twoFactorVerify, type TwoFactorStatus,
} from "@/lib/admin";
import { ApiError } from "@/lib/api";

const NAV = [
  ["/admin", "Tableau de bord"],
  ["/admin/annonces", "Annonces à modérer"],
  ["/admin/passeports", "Passeports"],
  ["/admin/abonnements", "Abonnements"],
  ["/admin/verifications", "Agences"],
  ["/admin/signalements", "Signalements"],
  ["/admin/utilisateurs", "Utilisateurs"],
  ["/admin/notifications", "Notifications"],
] as const;

const message = (e: unknown) => (e instanceof ApiError ? e.message : "Une erreur est survenue.");

function Frame({ title, children }: { title: string; children: ReactNode }) {
  return (
    <main className="mx-auto flex w-full max-w-md flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">{title}</h1>
      {children}
    </main>
  );
}

/** Activation du second facteur : secret à saisir dans l'application d'authentification, puis code de confirmation. */
function Setup({ onDone }: { onDone: () => void }) {
  const [secret, setSecret] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [codes, setCodes] = useState<string[] | null>(null);
  const [code, setCode] = useState("");
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  useEffect(() => {
    twoFactorSetup().then((r) => setSecret(r.data)).catch((e) => setError(message(e)));
  }, []);

  if (codes) {
    return (
      <Frame title="Codes de secours">
        <p>Conservez ces codes en lieu sûr : chacun ne sert qu&apos;une fois et ils ne seront plus affichés.</p>
        <ul className="grid grid-cols-2 gap-1 font-mono">
          {codes.map((c) => <li key={c}>{c}</li>)}
        </ul>
        <button className="rounded-lg bg-primary hover:bg-primary-hover px-4 py-2 text-white" onClick={onDone}>J&apos;ai enregistré mes codes</button>
      </Frame>
    );
  }
  return (
    <Frame title="Activer la double authentification">
      <p>Elle est obligatoire pour accéder à l&apos;administration. Ajoutez ce compte dans une application d&apos;authentification (saisie manuelle de la clé), puis entrez le code à 6 chiffres.</p>
      {secret && <p className="rounded-lg border border-dashed border-line-strong p-3 font-mono break-all">{secret.secret}</p>}
      <form
        className="flex flex-col gap-3"
        onSubmit={async (e) => {
          e.preventDefault();
          setPending(true); setError("");
          try { setCodes((await twoFactorConfirm(code)).data.recovery_codes); } catch (err) { setError(message(err)); }
          setPending(false);
        }}
      >
        <Field label="Code à 6 chiffres" name="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} onChange={(e) => setCode(e.target.value)} />
        {error && <FormMessage kind="error">{error}</FormMessage>}
        <SubmitButton pending={pending}>Activer</SubmitButton>
      </form>
    </Frame>
  );
}

function Verify({ left, onDone }: { left: number; onDone: () => void }) {
  const [recovery, setRecovery] = useState(false);
  const [value, setValue] = useState("");
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);
  return (
    <Frame title="Double authentification">
      <form
        className="flex flex-col gap-3"
        onSubmit={async (e) => {
          e.preventDefault();
          setPending(true); setError("");
          try { await twoFactorVerify(recovery ? { recovery_code: value } : { code: value }); onDone(); } catch (err) { setError(message(err)); }
          setPending(false);
        }}
      >
        <Field
          label={recovery ? "Code de secours" : "Code à 6 chiffres"} name="code" autoComplete="one-time-code" maxLength={recovery ? 20 : 6}
          inputMode={recovery ? "text" : "numeric"} value={value} onChange={(e) => setValue(e.target.value)}
          hint={recovery ? undefined : `Codes de secours restants : ${left}`}
        />
        {error && <FormMessage kind="error">{error}</FormMessage>}
        <SubmitButton pending={pending}>Valider</SubmitButton>
      </form>
      <button className="text-sm underline" onClick={() => { setRecovery(!recovery); setValue(""); setError(""); }}>
        {recovery ? "Utiliser l'application d'authentification" : "Utiliser un code de secours"}
      </button>
    </Frame>
  );
}

/** Garde de l'espace /admin. L'API refuse de toute façon (403) : ce composant n'est qu'un confort d'affichage. */
export default function AdminShell({ children }: { children: ReactNode }) {
  const { user, loading, signOut } = useRequireAuth();
  const [status, setStatus] = useState<TwoFactorStatus | null>(null);
  const [error, setError] = useState("");
  const reload = useCallback(() => {
    twoFactorStatus().then((r) => setStatus(r.data)).catch((e) => setError(message(e)));
  }, []);

  useEffect(() => {
    if (user && isAdmin(user.roles)) reload();
  }, [user, reload]);

  if (loading || !user) return <Frame title="Administration"><p>Chargement…</p></Frame>;
  if (!isAdmin(user.roles)) return <Frame title="Accès refusé"><p>Cet espace est réservé à l&apos;équipe ChezVous.</p><Link className="underline" href="/">Retour</Link></Frame>;
  if (error) return <Frame title="Administration"><FormMessage kind="error">{error}</FormMessage></Frame>;
  if (!status) return <Frame title="Administration"><p>Chargement…</p></Frame>;
  if (status.required && !status.enabled) return <Setup onDone={reload} />;
  if (status.required && !status.verified) return <Verify left={status.recovery_codes_left} onDone={reload} />;

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 p-6">
      <header className="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-3">
        <nav aria-label="Administration" className="flex flex-wrap gap-4">
          {NAV.map(([href, label]) => <Link key={href} href={href} className="underline-offset-4 hover:underline">{label}</Link>)}
        </nav>
        <button className="text-sm underline" onClick={() => signOut()}>Se déconnecter</button>
      </header>
      {children}
    </div>
  );
}
