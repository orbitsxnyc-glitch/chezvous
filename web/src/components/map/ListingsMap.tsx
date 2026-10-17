"use client";

import "leaflet/dist/leaflet.css";
import L from "leaflet";
import { useEffect, useRef, useState } from "react";
import { formatPrice, type Transaction } from "@/lib/format";
import { bboxParam, compactPrice, DAKAR, DEFAULT_ZOOM, fetchMapPoints, MAP_ATTRIBUTION, MAP_TILES, type MapPoint } from "@/lib/map";

/** Infobulle construite avec le DOM (textContent) : les titres viennent des annonceurs, jamais de HTML injecté. */
function popup(p: MapPoint): HTMLElement {
  const root = document.createElement("a");
  root.href = `/annonces/${encodeURIComponent(p.reference)}`;
  root.className = "block w-52 text-ink no-underline";
  if (p.cover) {
    const img = document.createElement("img");
    img.src = p.cover;
    img.alt = "";
    img.className = "mb-2 h-24 w-full rounded-md bg-sand-deep object-cover";
    img.onerror = () => img.remove();
    root.append(img);
  }
  const title = document.createElement("strong");
  title.className = "block text-sm leading-snug";
  title.textContent = p.title ?? "Annonce";
  const price = document.createElement("span");
  price.className = "block text-sm font-extrabold text-primary";
  price.textContent = formatPrice(p.price_xof, p.transaction as Transaction);
  const where = document.createElement("span");
  where.className = "block text-xs text-muted";
  where.textContent = [p.property_type_label, p.locality].filter(Boolean).join(" · ");
  root.append(title, price, where);
  return root;
}

function pill(p: MapPoint): L.DivIcon {
  const el = document.createElement("span");
  el.className = "cv-pin";
  el.textContent = compactPrice(p.price_xof);
  return L.divIcon({ html: el, className: "cv-pin-wrap", iconSize: [58, 28], iconAnchor: [29, 14] });
}

/**
 * Carte des annonces : charge les points visibles (API `/listings/map`) à chaque déplacement.
 * Positions approximatives par conception (jamais l'adresse exacte). Les filtres arrivent sérialisés (`filtersKey`) : en changer recharge la carte.
 */
export default function ListingsMap({ filtersKey, height = "100%" }: { filtersKey: string; height?: string }) {
  const node = useRef<HTMLDivElement>(null);
  const [status, setStatus] = useState<{ count: number; truncated: boolean } | "loading" | "error">("loading");

  useEffect(() => {
    if (!node.current) return;
    const filters = JSON.parse(filtersKey) as Record<string, string>;
    const map = L.map(node.current, { center: DAKAR, zoom: DEFAULT_ZOOM });
    L.tileLayer(MAP_TILES, { attribution: MAP_ATTRIBUTION, maxZoom: 18 }).addTo(map);
    const layer = L.layerGroup().addTo(map);
    let controller: AbortController | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const load = () => {
      controller?.abort();
      controller = new AbortController();
      const b = map.getBounds();
      setStatus("loading");
      fetchMapPoints(bboxParam(b.getWest(), b.getSouth(), b.getEast(), b.getNorth()), filters, controller.signal)
        .then((r) => {
          layer.clearLayers();
          for (const p of r.data) {
            L.marker([p.lat, p.lon], { icon: pill(p), title: `${p.title ?? "Annonce"} — ${formatPrice(p.price_xof, p.transaction as Transaction)}`, riseOnHover: true }).bindPopup(popup(p)).addTo(layer);
          }
          setStatus({ count: r.meta.count, truncated: r.meta.truncated });
        })
        .catch((e: unknown) => {
          if (e instanceof DOMException && e.name === "AbortError") return;
          setStatus("error");
        });
    };
    map.on("moveend", () => {
      clearTimeout(timer);
      timer = setTimeout(load, 350);
    });
    load();
    return () => {
      clearTimeout(timer);
      controller?.abort();
      map.remove();
    };
  }, [filtersKey]);

  const text =
    status === "loading" ? "Chargement…" : status === "error" ? "Carte indisponible : déplacez la carte pour réessayer" : `${status.count} bien${status.count > 1 ? "s" : ""} dans cette zone${status.truncated ? " (zoomez pour voir les autres)" : ""}`;

  return (
    <div className="relative" style={{ height }}>
      <div ref={node} role="region" aria-label="Carte des annonces" className="size-full min-h-80 rounded-xl border border-line" />
      <p role="status" className="pointer-events-none absolute top-3 left-1/2 z-[500] -translate-x-1/2 rounded-full bg-white px-3 py-1 text-xs font-bold shadow-card">{text}</p>
    </div>
  );
}
