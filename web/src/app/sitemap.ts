import type { MetadataRoute } from "next";
import { connection } from "next/server";
import { getNeighborhoods, getSitemapListings } from "@/lib/listings";
import { SEO_ROUTES, TYPE_PAGES, seoPath, type SeoTransaction } from "@/lib/seo";

const SITE = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  // Calculé à la demande : le build (et la CI) ne doivent pas dépendre de la disponibilité de l'API.
  await connection();
  const [listings, neighborhoods] = await Promise.all([getSitemapListings(), getNeighborhoods()]);
  const seoPages = (Object.keys(SEO_ROUTES) as SeoTransaction[]).flatMap((tr) =>
    TYPE_PAGES.flatMap((t) => neighborhoods.map((n) => ({ url: `${SITE}${seoPath(tr, t.slug, n.slug)}`, changeFrequency: "daily" as const, priority: 0.6 }))),
  );
  return [
    { url: SITE, changeFrequency: "daily", priority: 1 },
    { url: `${SITE}/annonces`, changeFrequency: "hourly", priority: 0.9 },
    ...seoPages,
    ...listings.map((l) => ({ url: `${SITE}/annonces/${l.reference}`, lastModified: l.updated_at, changeFrequency: "daily" as const, priority: 0.7 })),
  ];
}
