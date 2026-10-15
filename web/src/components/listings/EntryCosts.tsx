import type { ListingDetail } from "@/lib/listings";

const fmt = (n: number | null) => (n === null ? "Non renseigné" : `${new Intl.NumberFormat("fr-FR").format(n).replace(/ | /g, " ")} FCFA`);

/** Montants renseignés par l'annonceur, sans total calculé (règles légales à valider avant automatisation). */
export function EntryCosts({ costs }: { costs: NonNullable<ListingDetail["entry_costs"]> }) {
  const rows: [string, number | null][] = [
    ["Loyer", costs.rent_xof],
    [`Caution${costs.deposit_months !== null ? ` (${costs.deposit_months} mois)` : ""}`, costs.deposit_xof],
    [`Avance${costs.advance_months !== null ? ` (${costs.advance_months} mois)` : ""}`, costs.advance_xof],
    ["Frais d'agence", costs.agency_fee_xof],
    ["Autres frais", costs.other_fees_xof],
  ];
  return (
    <section aria-labelledby="couts" className="rounded-xl border border-sand-deep bg-sand p-6">
      <h2 id="couts" className="text-xl font-extrabold">
        Frais à l&apos;entrée
      </h2>
      <dl className="mt-4">
        {rows.map(([label, value]) => (
          <div key={label} className="flex justify-between gap-5 border-b border-[#e2d8c8] py-3 text-sm last:border-0">
            <dt>{label}</dt>
            <dd className="font-bold">{fmt(value)}</dd>
          </div>
        ))}
      </dl>
      <p className="mt-4 rounded-md bg-warning-soft p-3 text-xs leading-relaxed text-[#6f4a16]">{costs.disclaimer} Aucun total n&apos;est calculé par ChezVous.</p>
    </section>
  );
}
