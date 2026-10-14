import { CHECK_LABELS } from "@/lib/format";
import type { ListingDetail } from "@/lib/listings";
import { AvailabilityBadge } from "./AvailabilityBadge";

/** Passeport du Bien : identité, disponibilité datée et contrôles explicites. */
export function PassportPanel({ passport }: { passport: ListingDetail["passport"] }) {
  return (
    <section aria-labelledby="passeport" className="flex flex-col gap-3 rounded border border-zinc-300 p-4">
      <h2 id="passeport" className="text-lg font-semibold">
        Passeport du bien {passport.reference ? `· ${passport.reference}` : ""}
      </h2>
      <AvailabilityBadge state={passport.availability.state} confirmedAt={passport.availability.confirmed_at} />
      {passport.checks.length > 0 ? (
        <ul className="flex flex-col gap-1">
          {passport.checks.map((c) => (
            <li key={c.type}>
              ✓ {CHECK_LABELS[c.type] ?? c.type}
              {c.performed_at ? ` (${new Date(c.performed_at).toLocaleDateString("fr-FR")})` : ""}
            </li>
          ))}
        </ul>
      ) : (
        <p>Aucun contrôle enregistré pour le moment.</p>
      )}
      <p className="text-xs text-zinc-600">{passport.notice}</p>
    </section>
  );
}
