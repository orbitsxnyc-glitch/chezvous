"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { Field, SubmitButton } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { fieldErrors, generalError } from "@/lib/forms";
import { addRateOverride, blockDates, fetchHostUnit, formatDay, removeUnitPhoto, setRatePlan, setUnitCover, setUnitStatus, UNIT_LABELS, unblockDates, uploadUnitPhotos, type HostUnit } from "@/lib/stays";

const num = (f: FormData, k: string) => (f.get(k) ? Number(f.get(k)) : undefined);

export default function HostUnitView({ id }: { id: string }) {
  const { user, loading } = useRequireAuth();
  const [unit, setUnit] = useState<HostUnit | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [general, setGeneral] = useState("");
  const [info, setInfo] = useState("");
  const [pending, setPending] = useState(false);

  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchHostUnit(id).then((r) => !cancelled && setUnit(r.data)).catch((e) => !cancelled && setGeneral(generalError(e)));
    return () => { cancelled = true; };
  }, [user, id, tick]);

  async function run(action: () => Promise<unknown>, done: string, form?: HTMLFormElement) {
    setErrors({});
    setGeneral("");
    setInfo("");
    setPending(true);
    try {
      await action();
      setInfo(done);
      form?.reset();
      setTick((t) => t + 1);
    } catch (e) {
      setErrors(fieldErrors(e));
      setGeneral(generalError(e));
    } finally {
      setPending(false);
    }
  }

  const onRate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    void run(() => setRatePlan(id, { base_nightly_xof: Number(f.get("base")), min_nights: num(f, "min"), max_nights: num(f, "max") ?? null, cleaning_fee_xof: num(f, "cleaning") }), "Tarif enregistré.");
  };
  const onOverride = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const f = new FormData(form);
    void run(() => addRateOverride(id, { starts_on: String(f.get("from")), ends_on: String(f.get("to")), nightly_xof: Number(f.get("nightly")), min_nights: num(f, "min"), priority: num(f, "priority") }), "Tarif exceptionnel ajouté.", form);
  };
  const onPhotos = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const files = Array.from((form.elements.namedItem("photos") as HTMLInputElement).files ?? []);
    if (files.length === 0) return setGeneral("Choisissez au moins une photo.");
    void run(() => uploadUnitPhotos(id, files), "Photos envoyées : elles apparaissent après traitement.", form);
  };
  const onBlock = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = event.currentTarget;
    const f = new FormData(form);
    void run(() => blockDates(id, { starts_on: String(f.get("from")), ends_on: String(f.get("to")), note: String(f.get("note") ?? "") || undefined }), "Dates fermées.", form);
  };

  if (loading || (!unit && !general)) return <main className="mx-auto max-w-3xl p-6"><p>Chargement…</p></main>;
  if (!unit) return <main className="mx-auto max-w-3xl p-6"><p role="alert">{general}</p><Link href="/hote" className="underline">Retour</Link></main>;

  const rp = unit.rate_plan;
  const panel = "flex flex-col gap-3 rounded-xl border border-line bg-white p-5";
  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-5 px-5 py-8">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted"><Link href="/hote" className="underline">Espace hôte</Link> <span aria-hidden>/</span> {unit.name}</nav>
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-extrabold">{unit.name}</h1>
        <span className="rounded-md bg-[#f1ece6] px-2.5 py-1.5 text-xs font-bold">{UNIT_LABELS[unit.status]}</span>
      </header>
      {general && <p role="alert" className="text-danger">{general}</p>}
      {info && <p role="status" className="rounded-lg bg-success-soft p-3 text-sm text-[#0b6240]">{info}</p>}

      <section aria-labelledby="statut" className={panel}>
        <h2 id="statut" className="text-lg font-extrabold">Publication</h2>
        <p className="text-sm text-muted">Activez le logement quand son tarif est défini ; il devient visible après validation par notre équipe (photo et quartier requis).{unit.status === "active" && (unit.approved ? " Votre logement est validé et visible." : " En attente de validation.")}</p>
        <div className="flex flex-wrap gap-2">
          {unit.status !== "active" && unit.status !== "archived" && <button type="button" disabled={pending} onClick={() => run(() => setUnitStatus(id, "active"), "Logement activé : en attente de validation.")} className="min-h-11 rounded-lg bg-primary px-4 font-bold text-white hover:bg-primary-hover disabled:opacity-50">Activer</button>}
          {unit.status === "active" && <button type="button" disabled={pending} onClick={() => run(() => setUnitStatus(id, "paused"), "Logement mis en pause : il n'est plus visible.")} className="min-h-11 rounded-lg border border-line-strong px-4 font-bold hover:bg-sand">Mettre en pause</button>}
        </div>
      </section>

      <section aria-labelledby="photos" className={panel}>
        <h2 id="photos" className="text-lg font-extrabold">Photos</h2>
        {unit.photos && unit.photos.length > 0 ? (
          <ul className="flex flex-col gap-2 text-sm">
            {unit.photos.map((ph, i) => (
              <li key={ph.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-sand p-3">
                <span>Photo {i + 1}{ph.is_cover ? " · couverture" : ""}{ph.status !== "ready" ? ` · ${ph.status === "processing" ? "traitement en cours" : "échec du traitement"}` : ""}</span>
                <span className="flex gap-3">
                  {!ph.is_cover && ph.status === "ready" && <button type="button" disabled={pending} onClick={() => run(() => setUnitCover(id, ph.id), "Couverture modifiée.")} className="underline">Définir comme couverture</button>}
                  <button type="button" disabled={pending} onClick={() => run(() => removeUnitPhoto(id, ph.id), "Photo supprimée.")} className="text-danger underline">Supprimer</button>
                </span>
              </li>
            ))}
          </ul>
        ) : <p className="text-sm text-muted">Aucune photo : au moins une est requise pour la validation.</p>}
        <form onSubmit={onPhotos} className="flex flex-wrap items-end gap-3">
          <label className="flex flex-col gap-1 text-sm font-medium">Ajouter des photos
            <input name="photos" type="file" accept="image/jpeg,image/png,image/webp" multiple className="text-sm" />
          </label>
          <SubmitButton pending={pending}>Envoyer</SubmitButton>
        </form>
      </section>

      <section aria-labelledby="tarif" className={panel}>
        <h2 id="tarif" className="text-lg font-extrabold">Tarif de base</h2>
        <form onSubmit={onRate} className="grid gap-3 sm:grid-cols-2" noValidate>
          <Field label="Prix par nuit (FCFA)" name="base" type="number" min={1} step={1} required defaultValue={rp?.base_nightly_xof} error={errors.base_nightly_xof} />
          <Field label="Frais de ménage (FCFA)" name="cleaning" type="number" min={0} step={1} defaultValue={rp?.cleaning_fee_xof ?? 0} error={errors.cleaning_fee_xof} />
          <Field label="Nuits minimum" name="min" type="number" min={1} defaultValue={rp?.min_nights ?? 1} error={errors.min_nights} />
          <Field label="Nuits maximum" name="max" type="number" min={1} defaultValue={rp?.max_nights ?? undefined} error={errors.max_nights} />
          <div><SubmitButton pending={pending}>Enregistrer le tarif</SubmitButton></div>
        </form>
        <h3 className="mt-2 font-bold">Tarif exceptionnel (saison, week-end…)</h3>
        <form onSubmit={onOverride} className="grid gap-3 sm:grid-cols-2" noValidate>
          <Field label="Du (nuit incluse)" name="from" type="date" required error={errors.starts_on} />
          <Field label="Au (départ exclu)" name="to" type="date" required error={errors.ends_on} />
          <Field label="Prix par nuit (FCFA)" name="nightly" type="number" min={1} required error={errors.nightly_xof} />
          <Field label="Priorité" name="priority" type="number" min={-100} max={100} hint="La plus haute l'emporte en cas de chevauchement." />
          <div><SubmitButton pending={pending}>Ajouter</SubmitButton></div>
        </form>
      </section>

      <section aria-labelledby="calendrier" className={panel}>
        <h2 id="calendrier" className="text-lg font-extrabold">Calendrier : dates fermées</h2>
        {unit.calendar_blocks && unit.calendar_blocks.length > 0 ? (
          <ul className="flex flex-col gap-2 text-sm">
            {unit.calendar_blocks.map((b) => (
              <li key={b.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-sand p-3">
                <span>{formatDay(b.starts_on)} → {formatDay(b.ends_on)}{b.note ? ` · ${b.note}` : ""}</span>
                <button type="button" disabled={pending} onClick={() => run(() => unblockDates(id, b.id), "Dates rouvertes.")} className="text-danger underline">Rouvrir</button>
              </li>
            ))}
          </ul>
        ) : <p className="text-sm text-muted">Aucune fermeture.</p>}
        <form onSubmit={onBlock} className="grid gap-3 sm:grid-cols-2" noValidate>
          <Field label="Du" name="from" type="date" required error={errors.starts_on} />
          <Field label="Au (jour de réouverture)" name="to" type="date" required error={errors.ends_on} />
          <Field label="Note (facultatif)" name="note" maxLength={200} />
          <div className="flex items-end"><SubmitButton pending={pending}>Fermer ces dates</SubmitButton></div>
        </form>
      </section>
    </main>
  );
}
