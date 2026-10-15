"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { useAuth } from "@/components/AuthProvider";
import { Field, FormMessage, Page, SubmitButton } from "@/components/ui";
import { deleteMe, updateMe } from "@/lib/auth";
import { fieldErrors, generalError } from "@/lib/forms";

export default function ProfileView() {
  const router = useRouter();
  const { user, loading, setUser, signOut } = useAuth();
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<{ kind: "error" | "info"; text: string } | null>(null);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (!loading && !user) router.replace("/connexion");
  }, [loading, user, router]);

  if (loading || !user) return <Page title="Mon profil"><p>Chargement…</p></Page>;

  async function onSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setErrors({});
    setMessage(null);
    setPending(true);
    try {
      const { data } = await updateMe({
        name: String(form.get("name")).trim(),
        email: String(form.get("email")).trim() || null,
        city: String(form.get("city")).trim() || null,
        whatsapp_same_as_phone: form.get("whatsapp_same_as_phone") === "on",
      });
      setUser(data);
      setMessage({ kind: "info", text: "Profil mis à jour." });
    } catch (e) {
      setErrors(fieldErrors(e));
      setMessage({ kind: "error", text: generalError(e) });
    } finally {
      setPending(false);
    }
  }

  async function onLogout() {
    await signOut();
    router.push("/connexion");
  }

  async function onDelete(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const password = String(new FormData(event.currentTarget).get("password"));
    if (!window.confirm("Supprimer définitivement votre compte ? Vos données personnelles seront anonymisées.")) return;
    try {
      await deleteMe(password);
      setUser(null);
      router.push("/");
    } catch (e) {
      setMessage({ kind: "error", text: generalError(e) });
    }
  }

  return (
    <Page title="Mon profil">
      <p className="text-sm">Téléphone : {user.phone} (vérifié)</p>
      <form onSubmit={onSave} className="flex flex-col gap-4" noValidate>
        <Field label="Nom complet" name="name" defaultValue={user.name} required error={errors.name} />
        <Field label="E-mail" name="email" type="email" defaultValue={user.email ?? ""} error={errors.email} />
        <Field label="Ville" name="city" defaultValue={user.city ?? ""} error={errors.city} />
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" name="whatsapp_same_as_phone" defaultChecked={user.whatsapp === user.phone} />
          Mon numéro WhatsApp est le même que mon téléphone
        </label>
        {message && <FormMessage kind={message.kind}>{message.text}</FormMessage>}
        <SubmitButton pending={pending}>Enregistrer</SubmitButton>
      </form>
      <button type="button" onClick={onLogout} className="self-start underline">Se déconnecter</button>
      <hr />
      <form onSubmit={onDelete} className="flex flex-col gap-3">
        <h2 className="font-semibold">Supprimer mon compte</h2>
        <Field label="Confirmez avec votre mot de passe" name="password" type="password" autoComplete="current-password" required />
        <button type="submit" className="self-start rounded-lg border border-danger px-4 py-2 text-danger">Supprimer mon compte</button>
      </form>
    </Page>
  );
}
