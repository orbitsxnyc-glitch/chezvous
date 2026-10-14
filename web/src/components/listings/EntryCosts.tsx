import type { ListingDetail } from "@/lib/listings";

const fmt = (n: number | null) => (n === null ? "Non renseigné" : `${new Intl.NumberFormat("fr-FR").format(n).replace(/ | /g, " ")} FCFA`);

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
    <section aria-labelledby="couts" className="flex flex-col gap-2 rounded border border-zinc-300 p-4">
      <h2 id="couts" className="text-lg font-semibold">
        Coût d&apos;entrée
      </h2>
      <dl className="grid grid-cols-2 gap-1">
        {rows.map(([label, value]) => (
          <div key={label} className="contents">
            <dt>{label}</dt>
            <dd className="text-right">{fmt(value)}</dd>
          </div>
        ))}
      </dl>
      <p className="text-xs text-zinc-600">{costs.disclaimer}</p>
    </section>
  );
}
