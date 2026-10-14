"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useAuth } from "@/components/AuthProvider";
import { FormMessage, Page } from "@/components/ui";
import { acceptInvitation } from "@/lib/agency";
import { generalError } from "@/lib/forms";

export default function AcceptInvitation({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);
  const { user, loading } = useAuth();
  const [pending, setPending] = useState(false);
  const [done, setDone] = useState<string | null>(null);
  const [error, setError] = useState("");

  async function accept() {
    setPending(true);
    setError("");
    try {
      const { data } = await acceptInvitation(token);
      setDone(data.agency);
    } catch (e) {
      setError(generalError(e));
    } finally {
      setPending(false);
    }
  }

  if (loading) return <Page title="Invitation"><p>Chargement…</p></Page>;

  if (done) {
    return (
      <Page title="Invitation acceptée">
        <p>Vous faites maintenant partie de {done}.</p>
        <Link href="/agence" className="underline">Ouvrir l&apos;espace agence</Link>
      </Page>
    );
  }

  return (
    <Page title="Invitation à rejoindre une agence">
      {user ? (
        <>
          <p>Vous êtes connecté en tant que {user.name}. Acceptez-vous de rejoindre cette agence ?</p>
          {error && <FormMessage kind="error">{error}</FormMessage>}
          <button type="button" onClick={accept} disabled={pending} className="self-start rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50">
            {pending ? "Veuillez patienter…" : "Accepter l'invitation"}
          </button>
        </>
      ) : (
        <>
          <p>Connectez-vous (ou créez un compte avec le numéro invité), puis revenez sur ce lien.</p>
          <Link href="/connexion" className="underline">Se connecter</Link>
          <Link href="/inscription" className="underline">Créer un compte</Link>
        </>
      )}
    </Page>
  );
}
