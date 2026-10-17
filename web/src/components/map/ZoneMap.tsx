"use client";

import "leaflet/dist/leaflet.css";
import L from "leaflet";
import { useEffect, useRef } from "react";
import { MAP_ATTRIBUTION, MAP_TILES } from "@/lib/map";

/** Carte d'une annonce : zone approximative (cercle) sauf si l'annonceur a choisi la position exacte. */
export default function ZoneMap({ lat, lon, exact }: { lat: number; lon: number; exact: boolean }) {
  const node = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!node.current) return;
    const map = L.map(node.current, { center: [lat, lon], zoom: exact ? 16 : 14, scrollWheelZoom: false });
    L.tileLayer(MAP_TILES, { attribution: MAP_ATTRIBUTION, maxZoom: 18 }).addTo(map);
    if (exact) L.circleMarker([lat, lon], { radius: 9, color: "#c2410c", fillColor: "#c2410c", fillOpacity: 0.9 }).addTo(map);
    else L.circle([lat, lon], { radius: 350, color: "#c2410c", fillColor: "#c2410c", fillOpacity: 0.18, weight: 2 }).addTo(map);
    return () => {
      map.remove();
    };
  }, [lat, lon, exact]);
  return <div ref={node} role="region" aria-label={exact ? "Position du bien" : "Zone approximative du bien"} className="h-64 w-full rounded-xl border border-line" />;
}
