import { cacheLife } from "next/cache";
import { publicGet } from "./server-api";
import type { FreshnessState, Transaction } from "./format";
import type { SearchParams } from "./params";

export type Images = { thumb?: string; medium?: string; large?: string };

export type ListingSummary = {
  id: string;
  reference: string;
  transaction: Transaction;
  title: string | null;
  price_xof: number | null;
  negotiable: boolean;
  property_type: string | null;
  locality: { name: string; slug: string } | null;
  surface_m2: number | null;
  bedrooms: number | null;
  furnished: boolean;
  agency: { name: string; slug: string; verified: boolean } | null;
  cover: Images | null;
  availability: { state: FreshnessState; confirmed_at: string | null };
  published_at: string | null;
};

export type ListingDetail = {
  id: string;
  reference: string;
  transaction: Transaction;
  title: string | null;
  description: string | null;
  price_xof: number | null;
  negotiable: boolean;
  entry_costs: {
    rent_xof: number | null;
    deposit_xof: number | null;
    deposit_months: number | null;
    advance_xof: number | null;
    advance_months: number | null;
    agency_fee_xof: number | null;
    other_fees_xof: number | null;
    disclaimer: string;
  } | null;
  property: {
    reference: string | null;
    type: { code: string; label: string } | null;
    locality: { name: string; slug: string } | null;
    surface_m2: number | null;
    rooms: number | null;
    bedrooms: number | null;
    bathrooms: number | null;
    furnished: boolean;
    amenities: { code: string; label: string }[];
    location: { lat: number; lon: number; precision: string } | null;
    images: { id: string; is_cover: boolean; urls: Images }[];
  };
  passport: {
    reference: string | null;
    availability: { state: FreshnessState; confirmed_at: string | null };
    checks: { type: string; status: string; performed_at: string | null }[];
    notice: string;
  };
  advertiser: { name: string; account_type: string; agency?: { slug: string; verified: boolean } };
  published_at: string | null;
  expires_at: string | null;
};

export type SearchResult = { items: ListingSummary[]; nextCursor: string | null };

export async function searchListings(params: SearchParams): Promise<SearchResult | null> {
  "use cache";
  cacheLife("minutes");
  const res = await publicGet<{ data: ListingSummary[]; meta: { next_cursor: string | null } }>("/listings", params);
  return res ? { items: res.data, nextCursor: res.meta?.next_cursor ?? null } : null;
}

export async function getListing(reference: string): Promise<ListingDetail | null> {
  "use cache";
  cacheLife("minutes");
  const res = await publicGet<{ data: ListingDetail }>(`/listings/${encodeURIComponent(reference)}`);
  return res?.data ?? null;
}

export async function getSimilar(reference: string): Promise<ListingSummary[]> {
  "use cache";
  cacheLife("minutes");
  const res = await publicGet<{ data: ListingSummary[] }>(`/listings/${encodeURIComponent(reference)}/similar`);
  return res?.data ?? [];
}

export type Locality = { id: number; parent_id: number | null; level: string; name: string; slug: string };

export async function getNeighborhoods(): Promise<Locality[]> {
  "use cache";
  cacheLife("hours");
  const res = await publicGet<{ data: Locality[] }>("/localities", { level: "neighborhood" });
  return res?.data ?? [];
}

export async function getSitemapListings(): Promise<{ reference: string; updated_at: string }[]> {
  "use cache";
  cacheLife("hours");
  const res = await publicGet<{ data: { reference: string; updated_at: string }[] }>("/seo/sitemap");
  return res?.data ?? [];
}
