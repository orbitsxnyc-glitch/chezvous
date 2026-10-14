"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import type { FormEvent } from "react";
import { useAuth } from "@/components/AuthProvider";
import { Field, FormMessage, Page, SubmitButton } from "@/components/ui";
import { ApiError } from "@/lib/api";
import { login } from "@/lib/auth";
import { generalError } from "@/lib/forms";
import { normalizeSenegalPhone } from "@/lib/phone";

export default function LoginForm() {
  const router = useRouter();
  const { setUser } = useAuth();
  const verified = useSearchParams().get("verified") === "1";
  const [error, setError] = useState("");
  const [needsVerification, setNeedsVerification] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const phone = normalizeSenegalPhone(String(form.get("phone")));
    if (!phone) {
      setError("Numéro sénégalais invalide (ex. 77 123 45 67).");
      return;
    }
    setError("");
    setNeedsVerification(null);
    setPending(true);
    try {
      const { data } = await login(phone, String(form.get("password")));
      setUser(data);
      router.push("/profil");
    } catch (e) {
      if (e instanceof ApiError && e.code === "phone_not_verified") setNeedsVerification(phone);
      setError(generalError(e));
    } finally {
      setPending(false);
    }
  }

  return (
    <Page title="Connexion">
      {verified && <FormMessage kind="info">Numéro vérifié. Vous pouvez vous connecter.</FormMessage>}
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        <Field label="Téléphone" name="phone" type="tel" autoComplete="tel" required placeholder="77 123 45 67" />
        <Field label="Mot de passe" name="password" type="password" autoComplete="current-password" required />
        {error && <FormMessage kind="error">{error}</FormMessage>}
        {needsVerification && (
          <Link href={`/verification?phone=${encodeURIComponent(needsVerification)}`} className="text-sm underline">
            Vérifier mon numéro
          </Link>
        )}
        <SubmitButton pending={pending}>Se connecter</SubmitButton>
      </form>
      <p className="flex flex-col gap-1 text-sm">
        <Link href="/mot-de-passe-oublie" className="underline">Mot de passe oublié ?</Link>
        <Link href="/inscription" className="underline">Créer un compte</Link>
      </p>
    </Page>
  );
}
