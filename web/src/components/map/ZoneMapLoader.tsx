"use client";

import dynamic from "next/dynamic";

const ZoneMap = dynamic(() => import("./ZoneMap"), { ssr: false, loading: () => <div className="h-64 animate-pulse rounded-xl bg-sand-deep" aria-hidden /> });

export function ZoneMapLoader(props: { lat: number; lon: number; exact: boolean }) {
  return <ZoneMap {...props} />;
}
