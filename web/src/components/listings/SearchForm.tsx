import type { SearchParams } from "@/lib/params";
import type { Locality } from "@/lib/listings";
import { TYPE_PAGES } from "@/lib/seo";

/** Formulaire GET : fonctionne sans JavaScript et produit des URL partageables. */
export function SearchForm({ values, neighborhoods }: { values: SearchParams; neighborhoods: Locality[] }) {
  const select = "rounded-lg border border-line-strong px-2 py-2";
  const input = "rounded-lg border border-line-strong px-2 py-2";
  return (
    <form action="/annonces" method="get" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" role="search" aria-label="Rechercher un bien">
      <label className="flex flex-col gap-1 text-sm">
        Recherche
        <input name="q" defaultValue={values.q ?? ""} placeholder="Yoff, Almadies…" className={input} />
      </label>
      <label className="flex flex-col gap-1 text-sm">
        Transaction
        <select name="transaction" defaultValue={values.transaction ?? ""} className={select}>
          <option value="">Toutes</option>
          <option value="rent_long">Location</option>
          <option value="sale">Vente</option>
          <option value="rent_furnished_short">Meublé courte durée</option>
        </select>
      </label>
      <label className="flex flex-col gap-1 text-sm">
        Type de bien
        <select name="type" defaultValue={values.type ?? ""} className={select}>
          <option value="">Tous</option>
          {TYPE_PAGES.map((t) => (
            <option key={t.code} value={t.code}>
              {t.plural}
            </option>
          ))}
        </select>
      </label>
      <label className="flex flex-col gap-1 text-sm">
        Quartier
        <select name="locality" defaultValue={values.locality ?? ""} className={select}>
          <option value="">Tous</option>
          {neighborhoods.map((n) => (
            <option key={n.id} value={n.slug}>
              {n.name}
            </option>
          ))}
        </select>
      </label>
      <label className="flex flex-col gap-1 text-sm">
        Budget min (FCFA)
        <input name="price_min" type="number" min={0} inputMode="numeric" defaultValue={values.price_min ?? ""} className={input} />
      </label>
      <label className="flex flex-col gap-1 text-sm">
        Budget max (FCFA)
        <input name="price_max" type="number" min={0} inputMode="numeric" defaultValue={values.price_max ?? ""} className={input} />
      </label>
      <label className="flex flex-col gap-1 text-sm">
        Chambres min
        <input name="bedrooms_min" type="number" min={0} max={50} inputMode="numeric" defaultValue={values.bedrooms_min ?? ""} className={input} />
      </label>
      <label className="flex flex-col gap-1 text-sm">
        Trier par
        <select name="sort" defaultValue={values.sort ?? "relevance"} className={select}>
          <option value="relevance">Pertinence (disponibilité récente)</option>
          <option value="newest">Plus récentes</option>
          <option value="price_asc">Prix croissant</option>
          <option value="price_desc">Prix décroissant</option>
        </select>
      </label>
      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" name="furnished" value="1" defaultChecked={values.furnished === "1"} /> Meublé
      </label>
      <button type="submit" className="rounded-lg bg-primary hover:bg-primary-hover px-4 py-2 text-white sm:col-span-2 lg:col-span-1">
        Rechercher
      </button>
    </form>
  );
}
