"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { Field, SubmitButton } from "@/components/ui";
import { useRequireAuth } from "@/components/useRequireAuth";
import { fieldErrors, generalError } from "@/lib/forms";
import { fetchNeighborhoods, fetchPropertyTypes } from "@/lib/myListings";
import { createHostUnit, fetchHostUnits, UNIT_LABELS, type HostUnit } from "@/lib/stays";

export default function HostUnitsView() {
  const { user, loading } = useRequireAuth();
  const [units, setUnits] = useState<HostUnit[] | null>(null);
  const [types, setTypes] = useState<{ id: number; label: string }[]>([]);
  const [places, setPlaces] = useState<{ id: number; name: string }[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [general, setGeneral] = useState("");
  const [pending, setPending] = useState(false);

  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    Promise.all([fetchHostUnits(), fetchPropertyTypes(), fetchNeighborhoods()])
      .then(([u, t, n]) => {
        if (cancelled) return;
        setUnits(u.data);
        setTypes(t.data);
        setPlaces(n.data);
      })
      .catch((e) => !cancelled && setGeneral(generalError(e)));
    return () => { cancelled = true; };
  }, [user, tick]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    setErrors({});
    setGeneral("");
    setPending(true);
    try {
      await createHostUnit({
        name: String(f.get("name")),
        capacity_guests: Number(f.get("capacity_guests")),
        beds: f.get("beds") ? Number(f.get("beds")) : undefined,
        property_type_id: Number(f.get("property_type_id")),
        locality_id: f.get("locality_id") ? Number(f.get("locality_id")) : undefined,
      });
      event.currentTarget.reset();
      setTick((t) => t + 1);
    } catch (e) {
      setErrors(fieldErrors(e));
      setGeneral(generalError(e));
    } finally {
      setPending(false);
    }
  }

  const select = "min-h-12 rounded-lg border border-line-strong bg-white px-3 text-sm";
  return (
    <main className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-5 py-8">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-3xl font-extrabold">Espace hôte</h1>
        <Link href="/hote/demandes" className="rounded-lg border border-primary px-4 py-2 font-bold text-primary hover:bg-primary-soft">Demandes de séjour</Link>
      </header>
      {general && <p role="alert" className="text-danger">{general}</p>}
      <section aria-labelledby="unites">
        <h2 id="unites" className="text-lg font-extrabold">Mes logements</h2>
        {loading || units === null ? <p>Chargement…</p> : units.length === 0 ? <p className="mt-2 text-sm text-muted">Aucun logement. Ajoutez le premier ci-dessous.</p> : (
          <ul className="mt-3 flex flex-col gap-2">
            {units.map((u) => (
              <li key={u.id}><Link href={`/hote/unites/${u.id}`} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-white p-4 shadow-card hover:border-[#e5b999]">
                <span><strong>{u.name}</strong><span className="block text-xs text-muted">{u.property?.locality ?? "Quartier à renseigner"} · {u.capacity_guests} voyageur(s)</span></span>
                <span className="rounded-md bg-[#f1ece6] px-2.5 py-1.5 text-xs font-bold">{UNIT_LABELS[u.status]}</span>
              </Link></li>
            ))}
          </ul>
        )}
      </section>
      <section aria-labelledby="ajout" className="rounded-xl border border-line bg-white p-5">
        <h2 id="ajout" className="text-lg font-extrabold">Ajouter un logement</h2>
        <form onSubmit={create} className="mt-3 grid gap-3 sm:grid-cols-2" noValidate>
          <Field label="Nom du logement" name="name" required maxLength={120} error={errors.name} />
          <Field label="Voyageurs (max.)" name="capacity_guests" type="number" min={1} max={60} required error={errors.capacity_guests} />
          <Field label="Lits" name="beds" type="number" min={0} max={60} error={errors.beds} />
          <label className="flex flex-col gap-1 text-sm font-medium">Type de bien
            <select name="property_type_id" required className={select}><option value="">Choisir…</option>{types.map((t) => <option key={t.id} value={t.id}>{t.label}</option>)}</select>
          </label>
          <label className="flex flex-col gap-1 text-sm font-medium">Quartier
            <select name="locality_id" className={select}><option value="">Choisir…</option>{places.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select>
          </label>
          <div className="flex items-end"><SubmitButton pending={pending}>Créer le brouillon</SubmitButton></div>
        </form>
        <p className="mt-3 text-xs text-muted">Un logement devient visible après : tarif, au moins une photo, activation, puis validation par notre équipe.</p>
      </section>
    </main>
  );
}
