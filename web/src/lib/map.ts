import { API_URL } from "./api";

/** Fonds de carte : OpenStreetMap par défaut (gratuit, sans compte). Réglables sans toucher au code pour changer de fournisseur. */
export const MAP_TILES = process.env.NEXT_PUBLIC_MAP_TILES_URL ?? "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
export const MAP_ATTRIBUTION = process.env.NEXT_PUBLIC_MAP_ATTRIBUTION ?? '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">contributeurs OpenStreetMap</a>';

/** Dakar : centre et niveau de zoom de départ. */
export const DAKAR: [number, number] = [14.7167, -17.4677];
export const DEFAULT_ZOOM = 12;

export type MapPoint = {
  id: string; reference: string; transaction: string; price_xof: number | null; title: string | null;
  lat: number; lon: number; cover: string | null; locality: string | null; property_type_label: string | null;
};
/** Groupe d'annonces (carte dézoomée) : l'API ne renvoie plus d'annonces individuelles à ce niveau de zoom. */
export type MapCluster = { cluster: true; count: number; lat: number; lon: number; min_price_xof: number | null };
export type MapItem = MapPoint | MapCluster;
export const isCluster = (i: MapItem): i is MapCluster => "cluster" in i && i.cluster === true;
export type MapResult = { data: MapItem[]; meta: { count: number; truncated: boolean; clustered?: boolean } };

/** Prix compact pour une pastille de carte : 450 000 -> « 450 k », 1 250 000 -> « 1,25 M ». */
export function compactPrice(xof: number | null): string {
  if (xof === null) return "—";
  if (xof >= 1_000_000) return `${(xof / 1_000_000).toFixed(2).replace(/\.?0+$/, "").replace(".", ",")} M`;
  if (xof >= 1_000) return `${Math.round(xof / 1_000)} k`;
  return String(xof);
}

/** Emprise « ouest,sud,est,nord » attendue par l'API (5° maximum de côté). */
export function bboxParam(west: number, south: number, east: number, north: number): string {
  const r = (n: number) => Number(n.toFixed(5));
  return [r(west), r(south), r(east), r(north)].join(",");
}

/** Conserve uniquement les filtres de recherche connus (jamais le curseur de pagination ni la vue). */
export function mapFilters(params: Record<string, string>): URLSearchParams {
  const keep = ["transaction", "type", "locality", "q", "price_min", "price_max", "surface_min", "surface_max", "bedrooms_min", "furnished"];
  const out = new URLSearchParams();
  for (const k of keep) if (params[k]) out.set(k, params[k]);
  return out;
}

export async function fetchMapPoints(bbox: string, zoom: number, params: Record<string, string>, signal?: AbortSignal): Promise<MapResult> {
  const query = mapFilters(params);
  query.set("bbox", bbox);
  query.set("zoom", String(Math.round(zoom)));
  const res = await fetch(`${API_URL}/api/v1/listings/map?${query}`, { headers: { Accept: "application/json" }, signal });
  if (!res.ok) throw new Error(`carte ${res.status}`);
  return (await res.json()) as MapResult;
}
