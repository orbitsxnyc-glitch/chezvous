"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import type { FormEvent } from "react";
import { Field, FormMessage, Page, SubmitButton } from "@/components/ui";
import { registerAccount, type AccountType } from "@/lib/auth";
import { fieldErrors, generalError } from "@/lib/forms";
import { normalizeSenegalPhone } from "@/lib/phone";

export default function RegisterForm() {
  const router = useRouter();
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [general, setGeneral] = useState("");
  const [pending, setPending] = useState(false);
  const [accountType, setAccountType] = useState<AccountType>("individual");

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const phone = normalizeSenegalPhone(String(form.get("phone")));
    if (!phone) {
      setErrors({ phone: "Numéro sénégalais invalide (ex. 77 123 45 67)." });
      return;
    }
    setErrors({});
    setGeneral("");
    setPending(true);
    try {
      const email = String(form.get("email") ?? "").trim();
      await registerAccount({
        name: String(form.get("name")).trim(),
        phone,
        email,
        password: String(form.get("password")),
        account_type: accountType,
        agency_name: accountType === "agency" ? String(form.get("agency_name")).trim() : undefined,
      });
      router.push(`/verification?phone=${encodeURIComponent(phone)}`);
    } catch (e) {
      setErrors(fieldErrors(e));
      setGeneral(generalError(e));
    } finally {
      setPending(false);
    }
  }

  return (
    <Page title="Créer un compte">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        <Field label="Nom complet" name="name" autoComplete="name" required error={errors.name} />
        <Field label="Téléphone" name="phone" type="tel" autoComplete="tel" required placeholder="77 123 45 67" error={errors.phone} />
        <Field label="E-mail" name="email" type="email" autoComplete="email" required error={errors.email} hint="Votre code de vérification vous est envoyé à cette adresse." />
        <Field
          label="Mot de passe"
          name="password"
          type="password"
          autoComplete="new-password"
          required
          minLength={8}
          hint="8 caractères minimum."
          error={errors.password}
        />
        <fieldset className="flex flex-col gap-1">
          <legend className="text-sm font-medium">Type de compte</legend>
          <label className="flex items-center gap-2">
            <input type="radio" name="account_type" value="individual" checked={accountType === "individual"} onChange={() => setAccountType("individual")} /> Particulier
          </label>
          <label className="flex items-center gap-2">
            <input type="radio" name="account_type" value="agency" checked={accountType === "agency"} onChange={() => setAccountType("agency")} /> Agence
          </label>
          {errors.account_type && <p role="alert" className="text-sm text-danger">{errors.account_type}</p>}
        </fieldset>
        {accountType === "agency" && <Field label="Nom de l'agence" name="agency_name" required error={errors.agency_name} />}
        {general && <FormMessage kind="error">{general}</FormMessage>}
        <SubmitButton pending={pending}>Recevoir mon code</SubmitButton>
      </form>
      <p className="text-sm">
        Déjà un compte ? <Link href="/connexion" className="underline">Se connecter</Link>
      </p>
    </Page>
  );
}
