"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import type { FormEvent } from "react";
import { Field, FormMessage, Page, SubmitButton } from "@/components/ui";
import { sendOtp, verifyOtp } from "@/lib/auth";
import { generalError } from "@/lib/forms";
import { normalizeSenegalPhone } from "@/lib/phone";

export default function VerifyForm() {
  const router = useRouter();
  const phone = normalizeSenegalPhone(useSearchParams().get("phone") ?? "");
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [pending, setPending] = useState(false);

  if (!phone) {
    return (
      <Page title="Vérifier mon compte">
        <p>Numéro manquant. <Link href="/inscription" className="underline">Recommencer l&apos;inscription</Link></p>
      </Page>
    );
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!phone) return;
    setError("");
    setInfo("");
    setPending(true);
    try {
      await verifyOtp(phone, String(new FormData(event.currentTarget).get("code")).trim());
      router.push("/connexion?verified=1");
    } catch (e) {
      setError(generalError(e) || "Code invalide ou expiré.");
    } finally {
      setPending(false);
    }
  }

  async function resend() {
    if (!phone) return;
    setError("");
    try {
      await sendOtp(phone, "verify_phone");
      setInfo("Si le numéro est valide, un nouveau code vient d'être envoyé.");
    } catch (e) {
      setError(generalError(e));
    }
  }

  return (
    <Page title="Vérifier mon compte">
      <p>Saisissez le code à 6 chiffres envoyé par e-mail pour le compte {phone}. Pensez à vérifier vos courriers indésirables.</p>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Field label="Code" name="code" inputMode="numeric" autoComplete="one-time-code" pattern="\d{6}" maxLength={6} required />
        {error && <FormMessage kind="error">{error}</FormMessage>}
        {info && <FormMessage kind="info">{info}</FormMessage>}
        <SubmitButton pending={pending}>Vérifier</SubmitButton>
      </form>
      <button type="button" onClick={resend} className="self-start text-sm underline">
        Renvoyer le code
      </button>
    </Page>
  );
}
