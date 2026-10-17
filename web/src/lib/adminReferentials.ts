import { apiFetch } from "./api";

type Data<T> = { data: T };

export type Simple = { id: number; code: string; label: string; is_active: boolean; kind?: string };
export type LocalityRow = { id: number; parent_id: number | null; level: string; name: string; slug: string; code: string | null; is_active: boolean };

export type SimpleKind = "property-types" | "amenities" | "moderation-reasons";
export const SIMPLE_TITLES: Record<SimpleKind, string> = { "property-types": "Types de biens", amenities: "Équipements", "moderation-reasons": "Motifs de modération" };
export const LEVEL_LABELS: Record<string, string> = { region: "Région", department: "Département", commune: "Commune", neighborhood: "Quartier" };

export const fetchSimple = (kind: SimpleKind) => apiFetch<Data<Simple[]>>(`/admin/referentials/${kind}`);
export const createSimple = (kind: SimpleKind, body: { code: string; label: string; kind?: string }) => apiFetch<Data<Simple>>(`/admin/referentials/${kind}`, { method: "POST", body });
export const updateSimple = (kind: SimpleKind, id: number, body: { label?: string; is_active?: boolean }) => apiFetch<Data<Simple>>(`/admin/referentials/${kind}/${id}`, { method: "PATCH", body });

export const fetchLocalities = () => apiFetch<Data<LocalityRow[]>>("/admin/referentials/localities");
export const createLocality = (body: { name: string; level: string; parent_id?: number; latitude?: number; longitude?: number }) => apiFetch<Data<LocalityRow>>("/admin/referentials/localities", { method: "POST", body });
export const updateLocality = (id: number, body: { name?: string; is_active?: boolean }) => apiFetch<Data<LocalityRow>>(`/admin/referentials/localities/${id}`, { method: "PATCH", body });

/** Code technique : minuscules, chiffres et tirets bas (immuable une fois créé). */
export const isValidCode = (code: string) => /^[a-z0-9_]{2,40}$/.test(code);

/** Décimal saisi (point ou virgule) -> nombre ; vide -> undefined ; invalide -> NaN. */
export function parseCoordinate(raw: string): number | undefined {
  const v = raw.trim().replace(",", ".");
  if (v === "") return undefined;
  return /^-?\d+(\.\d+)?$/.test(v) ? Number(v) : Number.NaN;
}
