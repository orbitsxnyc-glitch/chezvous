"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { useAuth } from "@/components/AuthProvider";
import { Field, FormMessage, SubmitButton } from "@/components/ui";
import { addFavorite, createInquiry, fetchFavorites, removeFavorite } from "@/lib/engagement";
import { generalError } from "@/lib/forms";

/** Contact par la plateforme : le téléphone de l'annonceur n'est jamais affiché ; l'annonceur recontacte via la demande. */
export function ContactPanel({ listingId, reference }: { listingId: string; reference: string }) {
  const router = useRouter();
  const { user, loading } = useAuth();
  const [favorite, setFavorite] = useState(false);
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchFavorites()
      .then((r) => !cancelled && setFavorite(r.data.some((f) => f.listing.id === listingId)))
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [user, listingId]);

  async function toggleFavorite() {
    setError("");
    try {
      if (favorite) await removeFavorite(listingId);
      else await addFavorite(listingId);
      setFavorite(!favorite);
    } catch (e) {
      setError(generalError(e));
    }
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPending(true);
    setError("");
    try {
      const { data } = await createInquiry(listingId, {
        message: String(form.get("message")).trim(),
        phone: String(form.get("phone") ?? "").trim() || undefined,
        preferred_channel: String(form.get("preferred_channel")),
      });
      router.push(`/demandes/${data.id}`);
    } catch (e) {
      setError(generalError(e) || "Vérifiez les informations saisies.");
    } finally {
      setPending(false);
    }
  }

  if (loading) return null;

  if (!user) {
    return (
      <section aria-labelledby="contact" className="flex flex-col gap-2 rounded-lg border border-line p-4">
        <h2 id="contact" className="text-lg font-semibold">Contacter l&apos;annonceur</h2>
        <p>Connectez-vous pour envoyer un message, demander une visite ou enregistrer ce bien en favori.</p>
        <p className="flex gap-4 underline">
          <Link href="/connexion">Se connecter</Link>
          <Link href="/inscription">Créer un compte</Link>
        </p>
      </section>
    );
  }

  return (
    <section aria-labelledby="contact" className="flex flex-col gap-3 rounded-lg border border-line p-4">
      <h2 id="contact" className="text-lg font-semibold">Contacter l&apos;annonceur</h2>
      <button type="button" onClick={toggleFavorite} aria-pressed={favorite} className="self-start rounded-lg border border-line-strong px-3 py-1 text-sm">
        {favorite ? "★ Retirer des favoris" : "☆ Ajouter aux favoris"}
      </button>
      <form onSubmit={onSubmit} className="flex flex-col gap-3" noValidate>
        <label className="flex flex-col gap-1 text-sm">
          Votre message
          <textarea name="message" required maxLength={2000} rows={4} defaultValue={`Bonjour, le bien ${reference} est-il toujours disponible ?`} className="rounded-lg border border-line-strong px-3 py-2" />
        </label>
        <Field label="Votre téléphone (facultatif, sinon celui de votre compte)" name="phone" type="tel" placeholder="77 123 45 67" hint="Il n'est visible que de l'annonceur." />
        <label className="flex flex-col gap-1 text-sm">
          Comment souhaitez-vous être recontacté ?
          <select name="preferred_channel" defaultValue="message" className="rounded-lg border border-line-strong px-2 py-2">
            <option value="message">Par message ici</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="phone">Appel téléphonique</option>
          </select>
        </label>
        {error && <FormMessage kind="error">{error}</FormMessage>}
        <SubmitButton pending={pending}>Envoyer ma demande</SubmitButton>
      </form>
      <p className="text-xs text-muted">Vous pourrez proposer des créneaux de visite dans la conversation.</p>
    </section>
  );
}
