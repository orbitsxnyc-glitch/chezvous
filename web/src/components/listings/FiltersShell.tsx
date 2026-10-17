"use client";

import { useState, useSyncExternalStore } from "react";
import type { ReactNode } from "react";

const QUERY = "(min-width: 1024px)";
const subscribe = (notify: () => void) => {
  const mq = window.matchMedia(QUERY);
  mq.addEventListener("change", notify);
  return () => mq.removeEventListener("change", notify);
};

/** Sur grand écran, les filtres restent affichés ; sur mobile ils se replient derrière un bouton (affichés d'office sans JavaScript). */
export function FiltersShell({ count, children }: { count: number; children: ReactNode }) {
  const desktop = useSyncExternalStore(subscribe, () => window.matchMedia(QUERY).matches, () => true);
  const [open, setOpen] = useState(false);
  if (desktop) return <>{children}</>;
  return (
    <div className="flex flex-col gap-3">
      <button type="button" aria-expanded={open} aria-controls="filtres" onClick={() => setOpen(!open)} className="flex min-h-12 items-center justify-between rounded-xl border border-line bg-white px-4 text-sm font-bold shadow-card">
        <span>Filtres{count > 0 ? ` (${count})` : ""}</span>
        <span aria-hidden>{open ? "−" : "+"}</span>
      </button>
      <div id="filtres" hidden={!open}>{children}</div>
    </div>
  );
}
