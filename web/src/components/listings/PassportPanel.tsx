import { CHECK_LABELS } from "@/lib/format";
import type { ListingDetail } from "@/lib/listings";
import { AvailabilityBadge } from "./AvailabilityBadge";

/** Passeport du Bien : identité, disponibilité datée et contrôles explicites (jamais de note globale). */
export function PassportPanel({ passport }: { passport: ListingDetail["passport"] }) {
  return (
    <section aria-labelledby="passeport" className="rounded-xl border border-[#f2c9a8] bg-gradient-to-br from-[#fff7f0] to-white p-6">
      <div className="flex items-center gap-3.5">
        <span className="flex size-12 items-center justify-center rounded-full bg-primary text-lg text-white" aria-hidden>✓</span>
        <div>
          <p className="text-xs font-extrabold tracking-[0.13em] text-primary uppercase">Passeport du bien</p>
          <h2 id="passeport" className="text-lg font-extrabold">{passport.reference ?? "Référence en attente"}</h2>
        </div>
      </div>
      <div className="mt-4">
        <AvailabilityBadge state={passport.availability.state} confirmedAt={passport.availability.confirmed_at} />
      </div>
      {passport.checks.length > 0 ? (
        <ul className="mt-5 grid gap-2.5 sm:grid-cols-2">
          {passport.checks.map((c) => (
            <li key={c.type} className="flex items-center gap-3 rounded-lg border border-[#f0ded0] bg-white p-3">
              <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-success-soft text-sm text-success" aria-hidden>✓</span>
              <span className="flex flex-col">
                <strong className="text-sm">{CHECK_LABELS[c.type] ?? c.type}</strong>
                {c.performed_at && <small className="text-xs text-muted">{new Date(c.performed_at).toLocaleDateString("fr-FR")}</small>}
              </span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-5 text-sm text-muted">Aucun contrôle enregistré pour le moment.</p>
      )}
      <p className="mt-5 flex gap-2.5 rounded-lg bg-info-soft p-3 text-xs leading-relaxed text-[#194b82]">
        <span aria-hidden>ⓘ</span>
        {passport.notice}
      </p>
    </section>
  );
}
