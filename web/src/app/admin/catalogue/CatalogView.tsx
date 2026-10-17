"use client";

import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { FormMessage } from "@/components/ui";
import { useAuth } from "@/components/AuthProvider";
import {
  AUDIENCE_LABELS, createPlan, FEATURE_LABELS, fetchInstructions, fetchPlans, fetchProducts, KIND_LABELS, LIMIT_LABELS, parseLimit, PERIOD_LABELS,
  saveInstructions, updatePlan, updateProduct, type AdminPlan, type AdminProduct, type Features, type Limits, type PaymentInstructions,
} from "@/lib/adminCatalog";
import { ApiError } from "@/lib/api";
import { METHOD_LABELS } from "@/lib/adminCommercial";

const msg = (e: unknown) => (e instanceof ApiError ? e.message : "Erreur.");
const input = "min-h-10 w-full rounded-lg border border-line-strong bg-white px-2 text-sm";

function PlanCard({ plan, canEdit, onSaved, onError }: { plan: AdminPlan; canEdit: boolean; onSaved: () => void; onError: (m: string) => void }) {
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    const limits: Partial<Record<keyof Limits, number | null>> = {};
    for (const key of Object.keys(LIMIT_LABELS) as (keyof Limits)[]) {
      const v = parseLimit(String(f.get(key) ?? ""));
      if (v === undefined) return onError(`« ${LIMIT_LABELS[key]} » doit être un nombre entier positif (ou vide : à fixer).`);
      limits[key] = v;
    }
    const price = parseLimit(String(f.get("price_xof") ?? ""));
    if (price === undefined || price === null) return onError("Le prix doit être un nombre entier de FCFA.");
    setBusy(true);
    try {
      await updatePlan(plan.code, {
        name: String(f.get("name")).trim(), price_xof: price, limits,
        features: Object.fromEntries((Object.keys(FEATURE_LABELS) as (keyof Features)[]).map((k) => [k, f.get(k) === "on"])),
        is_active: f.get("is_active") === "on",
      });
      onSaved();
    } catch (e) {
      onError(msg(e));
    }
    setBusy(false);
  }

  return (
    <li className="rounded-lg border border-line bg-white p-4">
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
        <div className="sm:col-span-2 flex flex-wrap items-center justify-between gap-2">
          <h3 className="text-lg font-extrabold">{plan.name} <span className="text-sm font-normal text-muted">({plan.code})</span></h3>
          <span className={`rounded-md px-2.5 py-1 text-xs font-bold ${plan.is_active ? "bg-success-soft text-[#0b6240]" : "bg-sand text-muted"}`}>
            {plan.is_active ? "Proposée aux clients" : "Non proposée"}
          </span>
        </div>
        <p className="text-sm text-muted sm:col-span-2">{AUDIENCE_LABELS[plan.audience] ?? plan.audience} · {PERIOD_LABELS[plan.period] ?? plan.period}{plan.free ? " · gratuite" : ""}{!plan.configured && !plan.free ? " · quotas à fixer" : ""}</p>
        <label className="flex flex-col gap-1 text-xs font-bold">Nom affiché
          <input name="name" defaultValue={plan.name} disabled={!canEdit} className={input} />
        </label>
        <label className="flex flex-col gap-1 text-xs font-bold">Prix (FCFA)
          <input name="price_xof" inputMode="numeric" defaultValue={plan.price_xof} disabled={!canEdit || plan.free} className={input} />
        </label>
        {(Object.keys(LIMIT_LABELS) as (keyof Limits)[]).map((k) => (
          <label key={k} className="flex flex-col gap-1 text-xs font-bold">{LIMIT_LABELS[k]} (vide = à fixer)
            <input name={k} inputMode="numeric" defaultValue={plan.limits[k] ?? ""} disabled={!canEdit} className={input} />
          </label>
        ))}
        <fieldset className="flex flex-wrap gap-x-5 gap-y-1 sm:col-span-2" disabled={!canEdit}>
          <legend className="text-xs font-bold">Fonctionnalités</legend>
          {(Object.keys(FEATURE_LABELS) as (keyof Features)[]).map((k) => (
            <label key={k} className="flex items-center gap-2 text-sm font-normal"><input type="checkbox" name={k} defaultChecked={plan.features[k]} /> {FEATURE_LABELS[k]}</label>
          ))}
        </fieldset>
        <label className="flex items-center gap-2 text-sm font-bold sm:col-span-2">
          <input type="checkbox" name="is_active" defaultChecked={plan.is_active} disabled={!canEdit} /> Proposer cette formule aux clients
        </label>
        {canEdit && (
          <div className="sm:col-span-2">
            <button type="submit" disabled={busy} className="min-h-10 rounded-lg bg-primary px-5 text-sm font-bold text-white hover:bg-primary-hover disabled:opacity-50">Enregistrer</button>
            <span className="ml-3 text-xs text-muted">Une modification ne change jamais un abonnement déjà souscrit (prix et quotas figés).</span>
          </div>
        )}
      </form>
    </li>
  );
}

function ProductRow({ p, canEdit, onSaved, onError }: { p: AdminProduct; canEdit: boolean; onSaved: () => void; onError: (m: string) => void }) {
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    const price = parseLimit(String(f.get("price_xof") ?? ""));
    if (price === undefined || price === null) return onError("Le prix doit être un nombre entier de FCFA.");
    try {
      await updateProduct(p.code, { price_xof: price, is_active: f.get("is_active") === "on" });
      onSaved();
    } catch (e) {
      onError(msg(e));
    }
  }
  return (
    <li className="rounded-lg border border-line bg-white p-4">
      <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
        <div className="min-w-48"><strong>{KIND_LABELS[p.kind] ?? p.kind}</strong><p className="text-xs text-muted">{p.duration_days} jours · {p.code}</p></div>
        <label className="flex flex-col gap-1 text-xs font-bold">Prix (FCFA)
          <input name="price_xof" inputMode="numeric" defaultValue={p.price_xof} disabled={!canEdit} className={`${input} w-40`} />
        </label>
        <label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_active" defaultChecked={p.is_active} disabled={!canEdit} /> Proposée</label>
        {canEdit && <button type="submit" className="min-h-10 rounded-lg bg-primary px-4 text-sm font-bold text-white">Enregistrer</button>}
      </form>
    </li>
  );
}

function Instructions({ initial, canEdit, onSaved, onError }: { initial: PaymentInstructions; canEdit: boolean; onSaved: () => void; onError: (m: string) => void }) {
  const [methods, setMethods] = useState(initial?.methods ?? [{ method: "wave", label: "Wave", details: "" }]);
  const [note, setNote] = useState(initial?.note ?? "");
  const set = (i: number, patch: Partial<(typeof methods)[number]>) => setMethods((m) => m.map((x, j) => (j === i ? { ...x, ...patch } : x)));
  return (
    <form
      className="flex flex-col gap-3 rounded-lg border border-line bg-white p-4"
      onSubmit={async (e) => {
        e.preventDefault();
        try { await saveInstructions({ methods, note: note.trim() || null }); onSaved(); } catch (err) { onError(msg(err)); }
      }}
    >
      {methods.map((m, i) => (
        <div key={i} className="grid gap-2 sm:grid-cols-[10rem_12rem_1fr_auto]">
          <select aria-label="Moyen" disabled={!canEdit} className={input} value={m.method} onChange={(e) => set(i, { method: e.target.value, label: METHOD_LABELS[e.target.value] ?? m.label })}>
            {Object.entries(METHOD_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
          <input aria-label="Libellé affiché" disabled={!canEdit} className={input} value={m.label} maxLength={60} onChange={(e) => set(i, { label: e.target.value })} />
          <input aria-label="Coordonnées de règlement" disabled={!canEdit} className={input} value={m.details} maxLength={500} placeholder="Numéro, titulaire, IBAN…" onChange={(e) => set(i, { details: e.target.value })} />
          {canEdit && methods.length > 1 && <button type="button" className="text-sm underline" onClick={() => setMethods((x) => x.filter((_, j) => j !== i))}>Retirer</button>}
        </div>
      ))}
      {canEdit && methods.length < 5 && <button type="button" className="self-start text-sm underline" onClick={() => setMethods((x) => [...x, { method: "transfer", label: METHOD_LABELS.transfer, details: "" }])}>Ajouter un moyen</button>}
      <label className="flex flex-col gap-1 text-xs font-bold">Note affichée aux clients (facultatif)
        <input disabled={!canEdit} className={input} value={note} maxLength={500} onChange={(e) => setNote(e.target.value)} />
      </label>
      {canEdit && <button type="submit" className="min-h-10 self-start rounded-lg bg-primary px-5 text-sm font-bold text-white">Enregistrer les coordonnées</button>}
      <p className="text-xs text-muted">Ces coordonnées sont affichées aux clients qui demandent une formule. Aucun paiement n&apos;est pris en ligne.</p>
    </form>
  );
}

export default function CatalogView() {
  const { user } = useAuth();
  const canEdit = !!user?.roles.includes("super_admin");
  const [plans, setPlans] = useState<AdminPlan[] | null>(null);
  const [products, setProducts] = useState<AdminProduct[] | null>(null);
  const [instructions, setInstructions] = useState<PaymentInstructions | undefined>(undefined);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [version, setVersion] = useState(0);
  const saved = () => { setNotice("Enregistré."); setError(""); setVersion((v) => v + 1); };
  const fail = (m: string) => { setError(m); setNotice(""); };

  useEffect(() => {
    let cancelled = false;
    Promise.all([fetchPlans(), fetchProducts(), fetchInstructions()])
      .then(([a, b, c]) => { if (!cancelled) { setPlans(a.data); setProducts(b.data); setInstructions(c.data); } })
      .catch((e) => !cancelled && setError(msg(e)));
    return () => { cancelled = true; };
  }, [version]);

  async function onCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    try {
      await createPlan({ code: String(f.get("code")).trim(), name: String(f.get("name")).trim(), audience: String(f.get("audience")), period: String(f.get("period")) });
      event.currentTarget.reset();
      saved();
    } catch (e) { fail(msg(e)); }
  }

  return (
    <section className="flex flex-col gap-6">
      <h1 className="text-2xl font-extrabold">Offres et tarifs</h1>
      {!canEdit && <FormMessage kind="info">Consultation seule : seul un super administrateur peut modifier le catalogue.</FormMessage>}
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {notice && <FormMessage kind="info">{notice}</FormMessage>}

      <div className="flex flex-col gap-3">
        <h2 className="text-xl font-extrabold">Formules d&apos;abonnement</h2>
        {plans === null && !error && <p>Chargement…</p>}
        <ul className="flex flex-col gap-3">{plans?.map((p) => <PlanCard key={`${p.code}-${version}`} plan={p} canEdit={canEdit} onSaved={saved} onError={fail} />)}</ul>
        {canEdit && (
          <form onSubmit={onCreate} className="grid gap-2 rounded-lg bg-sand p-4 sm:grid-cols-5">
            <h3 className="font-extrabold sm:col-span-5">Nouvelle formule (créée inactive, prix et quotas à fixer)</h3>
            <input name="code" required placeholder="code_unique" pattern="[a-z0-9_]+" className={input} aria-label="Code" />
            <input name="name" required placeholder="Nom affiché" className={input} aria-label="Nom" />
            <select name="audience" className={input} aria-label="Public">{Object.entries(AUDIENCE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
            <select name="period" className={input} aria-label="Période">{Object.entries(PERIOD_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
            <button type="submit" className="min-h-10 rounded-lg bg-primary px-4 text-sm font-bold text-white">Créer</button>
          </form>
        )}
      </div>

      <div className="flex flex-col gap-3">
        <h2 className="text-xl font-extrabold">Mises en avant</h2>
        <ul className="flex flex-col gap-3">{products?.map((p) => <ProductRow key={`${p.code}-${version}`} p={p} canEdit={canEdit} onSaved={saved} onError={fail} />)}</ul>
      </div>

      <div className="flex flex-col gap-3">
        <h2 className="text-xl font-extrabold">Coordonnées de règlement</h2>
        {instructions !== undefined && <Instructions key={version} initial={instructions} canEdit={canEdit} onSaved={saved} onError={fail} />}
      </div>
    </section>
  );
}
