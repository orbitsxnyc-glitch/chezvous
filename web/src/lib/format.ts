export type Transaction = "sale" | "rent_long" | "rent_furnished_short";

const nf = new Intl.NumberFormat("fr-FR");

/** « 450 000 FCFA / mois » pour une location, « 50 000 000 FCFA » pour une vente. */
export function formatPrice(xof: number | null, transaction: Transaction): string {
  if (xof === null) return "Prix non communiqué";
  const base = `${nf.format(xof).replace(/ | /g, " ")} FCFA`;
  if (transaction === "rent_long") return `${base} / mois`;
  if (transaction === "rent_furnished_short") return `${base} / nuit`;
  return base;
}

export const TRANSACTION_LABELS: Record<Transaction, string> = {
  rent_long: "Location",
  sale: "Vente",
  rent_furnished_short: "Location meublée courte durée",
};

export type FreshnessState = "available" | "reserved" | "to_reconfirm" | "stale";

/** Texte du badge de fraîcheur : toujours daté, jamais une simple promesse. */
export function freshnessLabel(state: FreshnessState, confirmedAt: string | null, now: Date = new Date()): string {
  const since = confirmedAt ? relativeSince(new Date(confirmedAt), now) : null;
  switch (state) {
    case "available":
      return since ? `Disponible · confirmé ${since}` : "Disponible";
    case "reserved":
      return since ? `Réservé · confirmé ${since}` : "Réservé";
    case "to_reconfirm":
      return since ? `À reconfirmer · dernière confirmation ${since}` : "À reconfirmer";
    default:
      return "Information ancienne : disponibilité à vérifier";
  }
}

export function relativeSince(date: Date, now: Date): string {
  const minutes = Math.max(0, Math.floor((now.getTime() - date.getTime()) / 60000));
  if (minutes < 60) return "il y a moins d'une heure";
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `il y a ${hours} h`;
  const days = Math.floor(hours / 24);
  return `il y a ${days} jour${days > 1 ? "s" : ""}`;
}

export const CHECK_LABELS: Record<string, string> = {
  phone: "Téléphone vérifié",
  identity: "Identité vérifiée",
  professional: "Professionnel vérifié",
  location: "Localisation vérifiée",
  document: "Document contrôlé",
  field_visit: "Visite terrain effectuée",
};
