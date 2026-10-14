"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import type { FormEvent } from "react";
import { Field, FormMessage, Page, SubmitButton } from "@/components/ui";
import { resetPassword, sendOtp } from "@/lib/auth";
import { fieldErrors, generalError } from "@/lib/forms";
import { normalizeSenegalPhone } from "@/lib/phone";

export default function ResetForm() {
  const router = useRouter();
  const [phone, setPhone] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [general, setGeneral] = useState("");
  const [pending, setPending] = useState(false);

  async function requestCode(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const normalized = normalizeSenegalPhone(String(new FormData(event.currentTarget).get("phone")));
    if (!normalized) {
      setErrors({ phone: "Numéro sénégalais invalide (ex. 77 123 45 67)." });
      return;
    }
    setErrors({});
    setGeneral("");
    setPending(true);
    try {
      await sendOtp(normalized, "password_reset");
      setPhone(normalized);
    } catch (e) {
      setGeneral(generalError(e));
    } finally {
      setPending(false);
    }
  }

  async function submitReset(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!phone) return;
    const form = new FormData(event.currentTarget);
    setErrors({});
    setGeneral("");
    setPending(true);
    try {
      await resetPassword({ phone, code: String(form.get("code")).trim(), password: String(form.get("password")) });
      router.push("/connexion");
    } catch (e) {
      setErrors(fieldErrors(e));
      setGeneral(generalError(e) || "Code invalide ou expiré.");
    } finally {
      setPending(false);
    }
  }

  if (phone === null) {
    return (
      <Page title="Mot de passe oublié">
        <form onSubmit={requestCode} className="flex flex-col gap-4" noValidate>
          <Field label="Téléphone" name="phone" type="tel" autoComplete="tel" required placeholder="77 123 45 67" error={errors.phone} />
          {general && <FormMessage kind="error">{general}</FormMessage>}
          <SubmitButton pending={pending}>Recevoir un code</SubmitButton>
        </form>
        <Link href="/connexion" className="text-sm underline">Retour à la connexion</Link>
      </Page>
    );
  }

  return (
    <Page title="Nouveau mot de passe">
      <p>Si le numéro {phone} est inscrit, un code vient d&apos;être envoyé.</p>
      <form onSubmit={submitReset} className="flex flex-col gap-4">
        <Field label="Code" name="code" inputMode="numeric" autoComplete="one-time-code" pattern="\d{6}" maxLength={6} required error={errors.code} />
        <Field label="Nouveau mot de passe" name="password" type="password" autoComplete="new-password" required minLength={8} error={errors.password} />
        {general && <FormMessage kind="error">{general}</FormMessage>}
        <SubmitButton pending={pending}>Changer mon mot de passe</SubmitButton>
      </form>
    </Page>
  );
}
