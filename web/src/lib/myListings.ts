import { apiFetch } from "./api";
import type { Transaction } from "./format";

type Data<T> = { data: T };

export type ImageStatus = "processing" | "ready" | "failed";
export type MyImage = { id: string; status: ImageStatus; is_cover: boolean; position: number; urls: { thumb?: string; medium?: string; large?: string } };

export type MyListing = {
  id: string;
  reference: string;
  status: string;
  transaction: Transaction;
  title: string | null;
  description: string | null;
  price_xof: number | null;
  negotiable: boolean;
  deposit_months: number | null;
  advance_months: number | null;
  agency_fee_xof: number | null;
  other_fees_xof: number | null;
  draft_step: number;
  rejection: { reason: string; comment: string | null } | null;
  expires_at: string | null;
  missing_fields: string[];
  property: {
    type_id: number;
    locality_id: number | null;
    address: string | null;
    surface_m2: number | null;
    rooms: number | null;
    bedrooms: number | null;
    bathrooms: number | null;
    furnished: boolean;
    amenity_ids: number[];
    images: MyImage[];
  };
};

export const STATUS_LABELS: Record<string, string> = {
  draft: "Brouillon", pending_moderation: "En modération", published: "Publiée", rejected: "Refusée", paused: "En pause",
  closed: "Clôturée", expired: "Expirée", hidden: "Masquée", archived: "Archivée",
};

/** Champ manquant -> libellé lisible (clés renvoyées par `missing_fields`). */
export const MISSING_LABELS: Record<string, string> = {
  title: "le titre", description: "la description", price_xof: "le prix", locality_id: "le quartier", surface_m2: "la surface",
  cover_image: "une photo de couverture",
};

export const describeMissing = (fields: string[]) => fields.map((f) => MISSING_LABELS[f] ?? f);

/** Entier saisi -> nombre, vide -> null (efface la valeur), invalide -> undefined (champ ignoré). */
export function toInt(value: string): number | null | undefined {
  const v = value.trim().replace(/\s/g, "");
  if (v === "") return null;
  return /^\d+$/.test(v) ? Number(v) : undefined;
}

export const hasProcessingImages = (l: MyListing) => l.property.images.some((i) => i.status === "processing");

export const fetchMyListings = () => apiFetch<{ data: MyListing[] }>("/my/listings");
export const fetchMyListing = (id: string) => apiFetch<Data<MyListing>>(`/my/listings/${encodeURIComponent(id)}`);
export const createListing = (body: { property_type_id: number; transaction: Transaction }) =>
  apiFetch<Data<MyListing>>("/my/listings", { method: "POST", body });
export const updateListing = (id: string, body: Record<string, unknown>) =>
  apiFetch<Data<MyListing>>(`/my/listings/${encodeURIComponent(id)}`, { method: "PATCH", body });
export const submitListing = (id: string) => apiFetch<Data<MyListing>>(`/my/listings/${encodeURIComponent(id)}/submit`, { method: "POST" });

export function uploadImages(id: string, files: File[]) {
  const form = new FormData();
  for (const f of files) form.append("images[]", f);
  return apiFetch<Data<MyListing>>(`/my/listings/${encodeURIComponent(id)}/images`, { method: "POST", body: form });
}
export const setCover = (id: string, image: string) => apiFetch<Data<MyListing>>(`/my/listings/${id}/images/${image}/cover`, { method: "PATCH" });
export const deleteImage = (id: string, image: string) => apiFetch<unknown>(`/my/listings/${id}/images/${image}`, { method: "DELETE" });

export type PropertyType = { id: number; code: string; label: string };
export type Amenity = { id: number; code: string; label: string };
export type Neighborhood = { id: number; name: string; slug: string };
export const fetchPropertyTypes = () => apiFetch<Data<PropertyType[]>>("/property-types");
export const fetchAmenities = () => apiFetch<Data<Amenity[]>>("/amenities");
export const fetchNeighborhoods = () => apiFetch<Data<Neighborhood[]>>("/localities?level=neighborhood");
