import { freshnessLabel, type FreshnessState } from "@/lib/format";

const STYLES: Record<FreshnessState, string> = {
  available: "bg-green-100 text-green-900",
  reserved: "bg-blue-100 text-blue-900",
  to_reconfirm: "bg-amber-100 text-amber-900",
  stale: "bg-zinc-200 text-zinc-800",
};

/** Le texte porte l'information (pas seulement la couleur), pour l'accessibilité. */
export function AvailabilityBadge({ state, confirmedAt }: { state: FreshnessState; confirmedAt: string | null }) {
  return (
    <p className={`inline-block self-start rounded px-2 py-1 text-xs ${STYLES[state]}`}>{freshnessLabel(state, confirmedAt)}</p>
  );
}
