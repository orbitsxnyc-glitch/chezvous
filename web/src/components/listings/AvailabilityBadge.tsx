import { freshnessLabel, type FreshnessState } from "@/lib/format";

const STYLES: Record<FreshnessState, string> = {
  available: "bg-success-soft text-[#0b6240]",
  reserved: "bg-warning-soft text-[#714105]",
  to_reconfirm: "bg-warning-soft text-[#714105]",
  stale: "bg-[#edf0ee] text-[#53605a]",
};

const ICONS: Record<FreshnessState, string> = { available: "✓", reserved: "◔", to_reconfirm: "◔", stale: "…" };

/** Le texte porte l'information (pas seulement la couleur), pour l'accessibilité. */
export function AvailabilityBadge({ state, confirmedAt }: { state: FreshnessState; confirmedAt: string | null }) {
  return (
    <p className={`inline-flex w-fit max-w-full items-center gap-1.5 self-start rounded-md px-2.5 py-1.5 text-[0.7rem] leading-tight font-bold ${STYLES[state]}`}>
      <span aria-hidden>{ICONS[state]}</span>
      {freshnessLabel(state, confirmedAt)}
    </p>
  );
}
