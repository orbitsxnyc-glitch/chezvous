"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { FormMessage, Page, SubmitButton } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { generalError } from "@/lib/forms";
import { createListing, fetchPropertyTypes, type PropertyType } from "@/lib/myListings";
import type { Transaction } from "@/lib/format";

export default function NewListingForm() {
  const router = useRouter();
  const { user, loading } = useRequireAuth();
  const [types, setTypes] = useState<PropertyType[]>([]);
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  useEffect(() => {
    fetchPropertyTypes().then((r) => setTypes(r.data)).catch(() => setError("Impossible de charger les types de bien."));
  }, []);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPending(true);
    setError("");
    try {
      const res = await createListing({ property_type_id: Number(form.get("type")), transaction: String(form.get("transaction")) as Transaction });
      router.push(`/mes-annonces/${res.data.id}`);
    } catch (e) {
      setError(generalError(e) || "Vérifiez les informations saisies.");
      setPending(false);
    }
  }

  if (loading || !user) return <Page title="Nouvelle annonce"><p>Chargement…</p></Page>;
  return (
    <Page title="Nouvelle annonce">
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <label className="flex flex-col gap-1 text-sm font-medium">
          Je propose
          <select name="transaction" className="rounded border border-zinc-400 px-3 py-2" defaultValue="rent_long">
            <option value="rent_long">Une location</option>
            <option value="sale">Une vente</option>
          </select>
        </label>
        <label className="flex flex-col gap-1 text-sm font-medium">
          Type de bien
          <select name="type" required className="rounded border border-zinc-400 px-3 py-2">
            {types.map((t) => <option key={t.id} value={t.id}>{t.label}</option>)}
          </select>
        </label>
        {error && <FormMessage kind="error">{error}</FormMessage>}
        <SubmitButton pending={pending}>Créer le brouillon</SubmitButton>
      </form>
    </Page>
  );
}
