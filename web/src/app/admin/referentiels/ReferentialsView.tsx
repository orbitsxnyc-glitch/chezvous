"use client";

import { useEffect, useState } from "react";
import type { FormEvent } from "react";
import { FormMessage } from "@/components/ui";
import {
  createLocality, createSimple, fetchLocalities, fetchSimple, isValidCode, LEVEL_LABELS, parseCoordinate, SIMPLE_TITLES, updateLocality, updateSimple,
  type LocalityRow, type Simple, type SimpleKind,
} from "@/lib/adminReferentials";
import { ApiError } from "@/lib/api";

const msg = (e: unknown) => (e instanceof ApiError ? e.message : "Erreur.");
const input = "min-h-10 rounded-lg border border-line-strong bg-white px-2 text-sm";
type Tab = "localities" | SimpleKind;
const TABS: [Tab, string][] = [["localities", "Quartiers et zones"], ["property-types", SIMPLE_TITLES["property-types"]], ["amenities", SIMPLE_TITLES.amenities], ["moderation-reasons", SIMPLE_TITLES["moderation-reasons"]]];

function Row({ name, sub, active, onRename, onToggle }: { name: string; sub: string; active: boolean; onRename: (n: string) => void; onToggle: () => void }) {
  const [value, setValue] = useState(name);
  return (
    <li className={`flex flex-wrap items-center gap-3 rounded-lg border border-line bg-white p-3 ${active ? "" : "opacity-60"}`}>
      <input aria-label="Libellé" className={`${input} min-w-56 flex-1`} value={value} onChange={(e) => setValue(e.target.value)} onBlur={() => value.trim() && value.trim() !== name && onRename(value.trim())} />
      <span className="text-xs text-muted">{sub}</span>
      <button className="text-sm font-bold underline" onClick={onToggle}>{active ? "Désactiver" : "Réactiver"}</button>
    </li>
  );
}

export default function ReferentialsView() {
  const [tab, setTab] = useState<Tab>("localities");
  const [simple, setSimple] = useState<Simple[] | null>(null);
  const [places, setPlaces] = useState<LocalityRow[] | null>(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [version, setVersion] = useState(0);
  const done = (m: string) => { setNotice(m); setError(""); setVersion((v) => v + 1); };
  const fail = (e: unknown) => { setError(msg(e)); setNotice(""); };

  useEffect(() => {
    let cancelled = false;
    const load = tab === "localities" ? fetchLocalities().then((r) => !cancelled && setPlaces(r.data)) : fetchSimple(tab).then((r) => !cancelled && setSimple(r.data));
    load.catch((e) => !cancelled && setError(msg(e)));
    return () => { cancelled = true; };
  }, [tab, version]);

  async function addSimple(event: FormEvent<HTMLFormElement>, kind: SimpleKind) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    const code = String(f.get("code")).trim();
    if (!isValidCode(code)) return setError("Le code technique ne contient que des minuscules, chiffres et tirets bas (il ne pourra plus être modifié).");
    try {
      await createSimple(kind, { code, label: String(f.get("label")).trim(), ...(kind === "moderation-reasons" ? { kind: String(f.get("kind")) } : {}) });
      event.currentTarget.reset();
      done("Ajouté.");
    } catch (e) { fail(e); }
  }

  async function addLocality(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    const lat = parseCoordinate(String(f.get("latitude")));
    const lon = parseCoordinate(String(f.get("longitude")));
    if (Number.isNaN(lat) || Number.isNaN(lon) || (lat === undefined) !== (lon === undefined)) return setError("Latitude et longitude vont ensemble, en nombres décimaux (ex. 14.7167 et -17.4677).");
    const parent = Number(f.get("parent_id"));
    try {
      await createLocality({ name: String(f.get("name")).trim(), level: String(f.get("level")), ...(parent ? { parent_id: parent } : {}), ...(lat !== undefined && lon !== undefined ? { latitude: lat, longitude: lon } : {}) });
      event.currentTarget.reset();
      done("Localité ajoutée.");
    } catch (e) { fail(e); }
  }

  const parents = (places ?? []).filter((p) => p.level !== "neighborhood");

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-extrabold">Référentiels</h1>
      <p className="text-sm text-muted">On ne supprime jamais un élément (des annonces y font référence) : on le désactive. Le code technique ne change plus une fois créé.</p>
      <div className="flex flex-wrap gap-1 rounded-xl bg-sand p-1" role="tablist">
        {TABS.map(([k, label]) => (
          <button key={k} role="tab" aria-selected={tab === k} onClick={() => { setTab(k); setError(""); setNotice(""); setSimple(null); setPlaces(null); }} className={`min-h-10 rounded-lg px-4 text-sm font-bold ${tab === k ? "bg-white text-primary shadow-card" : "text-muted"}`}>{label}</button>
        ))}
      </div>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {notice && <FormMessage kind="info">{notice}</FormMessage>}

      {tab === "localities" ? (
        <>
          {places === null && !error && <p>Chargement…</p>}
          <ul className="flex flex-col gap-2">
            {places?.map((p) => (
              <Row key={`${p.id}-${version}`} name={p.name} sub={`${LEVEL_LABELS[p.level] ?? p.level}${p.code ? ` · ${p.code}` : ""}`} active={p.is_active}
                onRename={(n) => updateLocality(p.id, { name: n }).then(() => done("Renommé.")).catch(fail)}
                onToggle={() => updateLocality(p.id, { is_active: !p.is_active }).then(() => done(p.is_active ? "Désactivé." : "Réactivé.")).catch(fail)} />
            ))}
          </ul>
          <form onSubmit={addLocality} className="grid gap-2 rounded-lg bg-sand p-4 sm:grid-cols-3">
            <h2 className="font-extrabold sm:col-span-3">Ajouter une localité</h2>
            <input name="name" required placeholder="Nom (ex. Mermoz)" className={input} aria-label="Nom" />
            <select name="level" defaultValue="neighborhood" className={input} aria-label="Niveau">{Object.entries(LEVEL_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
            <select name="parent_id" className={input} aria-label="Parent"><option value="">Sans parent (région)</option>{parents.map((p) => <option key={p.id} value={p.id}>{p.name} ({LEVEL_LABELS[p.level]})</option>)}</select>
            <input name="latitude" placeholder="Latitude (facultatif)" className={input} aria-label="Latitude" />
            <input name="longitude" placeholder="Longitude (facultatif)" className={input} aria-label="Longitude" />
            <button type="submit" className="min-h-10 rounded-lg bg-primary px-4 text-sm font-bold text-white">Ajouter</button>
            <p className="text-xs text-muted sm:col-span-3">Sans coordonnées, la localité n&apos;est pas utilisable pour la position approximative des annonces. Les points de repère sont à valider.</p>
          </form>
        </>
      ) : (
        <>
          {simple === null && !error && <p>Chargement…</p>}
          <ul className="flex flex-col gap-2">
            {simple?.map((s) => (
              <Row key={`${s.id}-${version}`} name={s.label} sub={`${s.code}${s.kind ? ` · ${s.kind === "rejection" ? "refus d'annonce" : "signalement"}` : ""}`} active={s.is_active}
                onRename={(n) => updateSimple(tab, s.id, { label: n }).then(() => done("Renommé.")).catch(fail)}
                onToggle={() => updateSimple(tab, s.id, { is_active: !s.is_active }).then(() => done(s.is_active ? "Désactivé." : "Réactivé.")).catch(fail)} />
            ))}
          </ul>
          <form onSubmit={(e) => addSimple(e, tab)} className="grid gap-2 rounded-lg bg-sand p-4 sm:grid-cols-4">
            <h2 className="font-extrabold sm:col-span-4">Ajouter</h2>
            <input name="code" required placeholder="code_technique" className={input} aria-label="Code technique" />
            <input name="label" required placeholder="Libellé affiché" className={input} aria-label="Libellé" />
            {tab === "moderation-reasons" ? (
              <select name="kind" className={input} aria-label="Usage"><option value="rejection">Refus d&apos;annonce</option><option value="report">Signalement</option></select>
            ) : <span />}
            <button type="submit" className="min-h-10 rounded-lg bg-primary px-4 text-sm font-bold text-white">Ajouter</button>
          </form>
        </>
      )}
    </section>
  );
}
