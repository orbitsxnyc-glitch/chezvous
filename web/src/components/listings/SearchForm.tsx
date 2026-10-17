import Link from "next/link";
import type { SearchParams } from "@/lib/params";
import type { Locality } from "@/lib/listings";
import { TYPE_PAGES } from "@/lib/seo";

const control = "min-h-11 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink";
const label = "flex flex-col gap-1.5 text-xs font-bold text-[#3f362f]";

/** Panneau de filtres du design. Formulaire GET : fonctionne sans JavaScript et produit des URL partageables. */
export function SearchForm({ values, neighborhoods, view = "liste" }: { values: SearchParams; neighborhoods: Locality[]; view?: "liste" | "carte" }) {
  const active = Object.keys(values).filter((k) => k !== "cursor" && k !== "sort").length;
  return (
    <form action="/annonces" method="get" role="search" aria-label="Filtres de recherche" className="flex flex-col gap-4 rounded-xl border border-line bg-white p-5 shadow-card">
      {view === "carte" && <input type="hidden" name="vue" value="carte" />}
      <div className="flex items-center justify-between">
        <h2 className="text-base font-extrabold">Filtres{active > 0 ? <span className="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs text-white">{active}</span> : null}</h2>
        {active > 0 && <Link href={view === "carte" ? "/annonces?vue=carte" : "/annonces"} className="text-xs font-bold text-primary underline">Réinitialiser</Link>}
      </div>
      <label className={label}>
        Recherche libre
        <input name="q" defaultValue={values.q ?? ""} placeholder="Yoff, Almadies, VDN…" className={control} />
      </label>
      <label className={label}>
        Transaction
        <select name="transaction" defaultValue={values.transaction ?? ""} className={control}>
          <option value="">Toutes</option>
          <option value="rent_long">Location</option>
          <option value="sale">Vente</option>
          <option value="rent_furnished_short">Meublé courte durée</option>
        </select>
      </label>
      <label className={label}>
        Type de bien
        <select name="type" defaultValue={values.type ?? ""} className={control}>
          <option value="">Tous</option>
          {TYPE_PAGES.map((t) => <option key={t.code} value={t.code}>{t.plural}</option>)}
        </select>
      </label>
      <label className={label}>
        Quartier
        <select name="locality" defaultValue={values.locality ?? ""} className={control}>
          <option value="">Tous</option>
          {neighborhoods.map((n) => <option key={n.id} value={n.slug}>{n.name}</option>)}
        </select>
      </label>
      <fieldset className="grid grid-cols-2 gap-3">
        <legend className="mb-1.5 text-xs font-bold text-[#3f362f]">Budget (FCFA)</legend>
        <label className="sr-only" htmlFor="price_min">Budget minimum</label>
        <input id="price_min" name="price_min" type="number" min={0} inputMode="numeric" placeholder="Min" defaultValue={values.price_min ?? ""} className={control} />
        <label className="sr-only" htmlFor="price_max">Budget maximum</label>
        <input id="price_max" name="price_max" type="number" min={0} inputMode="numeric" placeholder="Max" defaultValue={values.price_max ?? ""} className={control} />
      </fieldset>
      <label className={label}>
        Chambres minimum
        <input name="bedrooms_min" type="number" min={0} max={50} inputMode="numeric" defaultValue={values.bedrooms_min ?? ""} className={control} />
      </label>
      <label className="flex min-h-11 items-center gap-2 text-sm font-semibold">
        <input type="checkbox" name="furnished" value="1" defaultChecked={values.furnished === "1"} className="size-5 accent-[#c2410c]" /> Meublé
      </label>
      <label className={label}>
        Trier par
        <select name="sort" defaultValue={values.sort ?? "relevance"} className={control}>
          <option value="relevance">Pertinence</option>
          <option value="newest">Plus récentes</option>
          <option value="price_asc">Prix croissant</option>
          <option value="price_desc">Prix décroissant</option>
        </select>
      </label>
      <button type="submit" className="min-h-12 rounded-lg bg-primary px-4 text-sm font-bold text-white shadow-card hover:bg-primary-hover">
        Appliquer les filtres
      </button>
    </form>
  );
}
