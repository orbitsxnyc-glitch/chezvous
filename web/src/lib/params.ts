export type SearchParams = Record<string, string>;

const ALLOWED = ["transaction", "type", "locality", "q", "price_min", "price_max", "surface_min", "surface_max", "bedrooms_min", "furnished", "sort", "cursor"];

/** Ne garde que les paramètres connus et non vides (aucune valeur du visiteur n'est transmise telle quelle à l'API sans filtre). */
export function cleanParams(input: Record<string, string | string[] | undefined>): SearchParams {
  const out: SearchParams = {};
  for (const key of ALLOWED) {
    const raw = input[key];
    const value = Array.isArray(raw) ? raw[0] : raw;
    if (value !== undefined && value.trim() !== "") out[key] = value.trim().slice(0, 100);
  }
  return out;
}
