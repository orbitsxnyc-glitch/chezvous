"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useState } from "react";
import type { FormEvent } from "react";
import { Field, FormMessage, SubmitButton } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { ApiError } from "@/lib/api";
import { fieldErrors, generalError } from "@/lib/forms";
import {
  deleteImage, describeMissing, fetchAmenities, fetchMyListing, fetchNeighborhoods, hasProcessingImages, setCover, STATUS_LABELS, submitListing,
  toInt, updateListing, uploadImages, type Amenity, type MyListing, type Neighborhood,
} from "@/lib/myListings";

const EDITABLE = ["draft", "rejected", "expired", "paused", "published"];

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-3 rounded border border-zinc-300 p-4">
      <h2 className="text-lg font-semibold">{title}</h2>
      {children}
    </section>
  );
}

/** Éditeur sobre en sections, chacune enregistrée explicitement (le design Figma viendra après). Les droits sont vérifiés par l'API. */
export default function ListingEditor({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const { user, loading } = useRequireAuth();
  const [listing, setListing] = useState<MyListing | null>(null);
  const [neighborhoods, setNeighborhoods] = useState<Neighborhood[]>([]);
  const [amenities, setAmenities] = useState<Amenity[]>([]);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState("");
  const [version, setVersion] = useState(0);
  const reload = useCallback(() => setVersion((v) => v + 1), []);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchMyListing(id)
      .then((r) => !cancelled && setListing(r.data))
      .catch((e) => !cancelled && setError(e instanceof ApiError && e.status === 404 ? "Annonce introuvable." : generalError(e)));
    return () => {
      cancelled = true;
    };
  }, [user, id, version]);

  useEffect(() => {
    fetchNeighborhoods().then((r) => setNeighborhoods(r.data)).catch(() => undefined);
    fetchAmenities().then((r) => setAmenities(r.data)).catch(() => undefined);
  }, []);

  // Les photos sont traitées en arrière-plan : on relit l'annonce tant qu'une photo est « en traitement ».
  const processing = listing ? hasProcessingImages(listing) : false;
  useEffect(() => {
    if (!processing) return;
    const timer = setTimeout(reload, 3000);
    return () => clearTimeout(timer);
  }, [processing, version, reload]);

  async function run(key: string, action: () => Promise<unknown>, done: string) {
    setPending(key);
    setError("");
    setNotice("");
    setErrors({});
    try {
      await action();
      setNotice(done);
      reload();
    } catch (e) {
      setErrors(fieldErrors(e));
      setError(generalError(e) || "Vérifiez les champs signalés.");
    }
    setPending("");
  }

  const intField = (form: FormData, name: string) => toInt(String(form.get(name) ?? ""));

  function saveInfo(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    const body: Record<string, unknown> = {
      title: String(f.get("title")).trim() || null,
      description: String(f.get("description")).trim() || null,
      negotiable: f.get("negotiable") === "on",
      draft_step: 2,
    };
    for (const name of ["price_xof", "deposit_months", "advance_months", "agency_fee_xof", "other_fees_xof"]) {
      const v = f.has(name) ? intField(f, name) : undefined;
      if (v !== undefined) body[name] = v;
    }
    void run("info", () => updateListing(id, body), "Informations enregistrées.");
  }

  function saveProperty(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    const body: Record<string, unknown> = {
      address: String(f.get("address")).trim() || null,
      furnished: f.get("furnished") === "on",
      amenity_ids: f.getAll("amenity_ids").map(Number),
      draft_step: 3,
    };
    const locality = Number(f.get("locality_id"));
    if (locality) body.locality_id = locality;
    for (const name of ["surface_m2", "rooms", "bedrooms", "bathrooms"]) {
      const v = intField(f, name);
      if (v !== undefined) body[name] = v;
    }
    void run("property", () => updateListing(id, body), "Bien enregistré.");
  }

  function onFiles(event: FormEvent<HTMLInputElement>) {
    const input = event.currentTarget;
    const files = Array.from(input.files ?? []);
    if (files.length === 0) return;
    void run("photos", () => uploadImages(id, files), "Photos envoyées : traitement en cours.").then(() => {
      input.value = "";
    });
  }

  if (loading || !user) return <main className="p-6">Chargement…</main>;
  if (!listing) return <main className="p-6">{error ? <FormMessage kind="error">{error}</FormMessage> : "Chargement…"}</main>;

  // Clés tirées des valeurs enregistrées : un rafraîchissement sans changement (suivi des photos) ne remet pas les champs en cours de saisie à zéro.
  const infoKey = JSON.stringify([listing.title, listing.description, listing.price_xof, listing.negotiable, listing.deposit_months, listing.advance_months, listing.agency_fee_xof, listing.other_fees_xof]);
  const p = listing.property;
  const propertyKey = JSON.stringify([p.locality_id, p.address, p.surface_m2, p.rooms, p.bedrooms, p.bathrooms, p.furnished, p.amenity_ids]);
  const rental = listing.transaction !== "sale";
  const canEdit = EDITABLE.includes(listing.status);
  const ready = listing.missing_fields.length === 0;
  const canSubmit = ["draft", "rejected", "expired"].includes(listing.status);

  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
      <Link href="/mes-annonces" className="text-sm underline">← Mes annonces</Link>
      <h1 className="text-2xl font-semibold">{listing.title ?? "Annonce sans titre"}</h1>
      <p className="text-sm">{listing.reference} · {STATUS_LABELS[listing.status] ?? listing.status}</p>
      {listing.status === "rejected" && listing.rejection && (
        <FormMessage kind="error">Refusée{listing.rejection.comment ? ` : ${listing.rejection.comment}` : ""}. Corrigez puis soumettez à nouveau.</FormMessage>
      )}
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {notice && <FormMessage kind="info">{notice}</FormMessage>}
      {!canEdit && <p>Cette annonce ne peut plus être modifiée dans son état actuel.</p>}

      {canEdit && (
        <>
          <Section title="1. L'annonce">
            <form key={infoKey} onSubmit={saveInfo} className="flex flex-col gap-3">
              <Field label="Titre" name="title" defaultValue={listing.title ?? ""} maxLength={150} error={errors.title} />
              <label className="flex flex-col gap-1 text-sm font-medium">
                Description
                <textarea name="description" rows={5} maxLength={5000} defaultValue={listing.description ?? ""} className="rounded border border-zinc-400 px-3 py-2" />
                {errors.description && <span role="alert" className="text-red-700">{errors.description}</span>}
              </label>
              <Field label={rental ? "Loyer mensuel (FCFA)" : "Prix (FCFA)"} name="price_xof" inputMode="numeric" defaultValue={listing.price_xof ?? ""} error={errors.price_xof} />
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="negotiable" defaultChecked={listing.negotiable} /> Prix négociable</label>
              {rental && (
                <>
                  <Field label="Caution (en mois de loyer)" name="deposit_months" inputMode="numeric" defaultValue={listing.deposit_months ?? ""} error={errors.deposit_months} />
                  <Field label="Avance (en mois de loyer)" name="advance_months" inputMode="numeric" defaultValue={listing.advance_months ?? ""} error={errors.advance_months} />
                  <Field label="Frais d'agence (FCFA)" name="agency_fee_xof" inputMode="numeric" defaultValue={listing.agency_fee_xof ?? ""} error={errors.agency_fee_xof} />
                  <Field label="Autres frais (FCFA)" name="other_fees_xof" inputMode="numeric" defaultValue={listing.other_fees_xof ?? ""} error={errors.other_fees_xof}
                    hint="Montants affichés tels que saisis, sans total calculé : à confirmer avec le visiteur." />
                </>
              )}
              <SubmitButton pending={pending === "info"}>Enregistrer</SubmitButton>
            </form>
          </Section>

          <Section title="2. Le bien">
            <form key={propertyKey} onSubmit={saveProperty} className="flex flex-col gap-3">
              <label className="flex flex-col gap-1 text-sm font-medium">
                Quartier
                <select name="locality_id" defaultValue={listing.property.locality_id ?? ""} className="rounded border border-zinc-400 px-3 py-2">
                  <option value="">Choisir…</option>
                  {neighborhoods.map((n) => <option key={n.id} value={n.id}>{n.name}</option>)}
                </select>
                {errors.locality_id && <span role="alert" className="text-red-700">{errors.locality_id}</span>}
              </label>
              <Field label="Adresse (jamais affichée publiquement)" name="address" defaultValue={listing.property.address ?? ""} error={errors.address} />
              <div className="grid grid-cols-2 gap-3">
                <Field label="Surface (m²)" name="surface_m2" inputMode="numeric" defaultValue={listing.property.surface_m2 ?? ""} error={errors.surface_m2} />
                <Field label="Pièces" name="rooms" inputMode="numeric" defaultValue={listing.property.rooms ?? ""} error={errors.rooms} />
                <Field label="Chambres" name="bedrooms" inputMode="numeric" defaultValue={listing.property.bedrooms ?? ""} error={errors.bedrooms} />
                <Field label="Salles de bain" name="bathrooms" inputMode="numeric" defaultValue={listing.property.bathrooms ?? ""} error={errors.bathrooms} />
              </div>
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="furnished" defaultChecked={listing.property.furnished} /> Meublé</label>
              <fieldset className="flex flex-wrap gap-3">
                <legend className="text-sm font-medium">Équipements</legend>
                {amenities.map((a) => (
                  <label key={a.id} className="flex items-center gap-1 text-sm">
                    <input type="checkbox" name="amenity_ids" value={a.id} defaultChecked={listing.property.amenity_ids.includes(a.id)} /> {a.label}
                  </label>
                ))}
              </fieldset>
              <SubmitButton pending={pending === "property"}>Enregistrer</SubmitButton>
            </form>
          </Section>

          <Section title="3. Photos">
            <p className="text-sm">JPEG, PNG ou WebP. La première photo marquée « couverture » illustre l&apos;annonce.</p>
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={onFiles} disabled={pending === "photos"} aria-label="Ajouter des photos" />
            {processing && <p role="status" className="text-sm">Traitement des photos en cours…</p>}
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {listing.property.images.map((img) => (
                <li key={img.id} className="flex flex-col gap-1 rounded border border-zinc-300 p-2">
                  {img.status === "ready" ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={img.urls.thumb ?? img.urls.medium} alt="" className="aspect-[4/3] w-full object-cover" />
                  ) : (
                    <p className="aspect-[4/3] text-sm">{img.status === "failed" ? "Échec du traitement" : "Traitement…"}</p>
                  )}
                  {img.is_cover ? <span className="text-xs font-medium">Couverture</span> : img.status === "ready" && (
                    <button type="button" className="text-left text-sm underline" onClick={() => run("cover", () => setCover(id, img.id), "Couverture modifiée.")}>Définir comme couverture</button>
                  )}
                  <button type="button" className="text-left text-sm underline" onClick={() => run("del", () => deleteImage(id, img.id), "Photo supprimée.")}>Supprimer</button>
                </li>
              ))}
            </ul>
          </Section>
        </>
      )}

      {canSubmit && (
        <Section title="4. Publier">
          {ready ? (
            <p>Votre annonce est complète. Elle sera relue par notre équipe avant publication.</p>
          ) : (
            <p>Il manque : {describeMissing(listing.missing_fields).join(", ")}.</p>
          )}
          <button
            disabled={!ready || pending === "submit"}
            className="self-start rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50"
            onClick={() => run("submit", () => submitListing(id), "Annonce envoyée en modération.")}
          >
            Soumettre à la modération
          </button>
        </Section>
      )}
    </main>
  );
}
