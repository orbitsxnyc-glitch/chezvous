import type { Transaction } from "./format";

/** Types de biens : code API, slug d'URL (pluriel), libellé pluriel pour les titres. */
export const TYPE_PAGES = [
  { code: "apartment", slug: "appartements", plural: "Appartements" },
  { code: "house", slug: "maisons-villas", plural: "Maisons et villas" },
  { code: "studio", slug: "studios", plural: "Studios" },
  { code: "room", slug: "chambres", plural: "Chambres" },
  { code: "land", slug: "terrains", plural: "Terrains" },
  { code: "office", slug: "bureaux", plural: "Bureaux" },
  { code: "shop", slug: "locaux-commerciaux", plural: "Locaux commerciaux" },
  { code: "warehouse", slug: "entrepots", plural: "Entrepôts" },
  { code: "building", slug: "immeubles", plural: "Immeubles" },
] as const;

export type SeoTransaction = Extract<Transaction, "rent_long" | "sale">;

export const SEO_ROUTES: Record<SeoTransaction, { segment: string; verb: string }> = {
  rent_long: { segment: "a-louer", verb: "à louer" },
  sale: { segment: "a-vendre", verb: "à vendre" },
};

export function typeBySlug(slug: string) {
  return TYPE_PAGES.find((t) => t.slug === slug) ?? null;
}

export function seoTitle(transaction: SeoTransaction, typePlural: string, localityName: string): string {
  return `${typePlural} ${SEO_ROUTES[transaction].verb} à ${localityName}`;
}

export function seoPath(transaction: SeoTransaction, typeSlug: string, localitySlug: string): string {
  return `/${SEO_ROUTES[transaction].segment}/${typeSlug}/${localitySlug}`;
}
