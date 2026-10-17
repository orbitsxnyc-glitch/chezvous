"use client";

import dynamic from "next/dynamic";

// Leaflet touche à `window` : chargement côté navigateur uniquement.
const ListingsMap = dynamic(() => import("./ListingsMap"), { ssr: false, loading: () => <div className="min-h-80 animate-pulse rounded-xl bg-sand-deep" aria-hidden /> });

export function ListingsMapLoader({ filters, height }: { filters: Record<string, string>; height?: string }) {
  return <ListingsMap filtersKey={JSON.stringify(filters)} height={height} />;
}
