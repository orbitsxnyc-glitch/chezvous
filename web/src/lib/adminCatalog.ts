import { apiFetch } from "./api";

type Data<T> = { data: T };

export type Limits = { active_listings: number | null; agents: number | null; photos_per_listing: number | null; featured_credits: number | null };
export type Features = { advanced_stats: boolean; badge: boolean; enriched_agency_page: boolean };
export type AdminPlan = {
  code: string; name: string; audience: string; price_xof: number; period: string; limits: Limits; features: Features;
  free: boolean; is_active: boolean; configured: boolean;
};
export type AdminProduct = { code: string; kind: string; duration_days: number; price_xof: number; is_active: boolean };
export type PaymentInstructions = { methods: { method: string; label: string; details: string }[]; note: string | null } | null;

export const AUDIENCE_LABELS: Record<string, string> = { individual: "Particuliers", agency: "Agences", both: "Tous" };
export const PERIOD_LABELS: Record<string, string> = { monthly: "par mois", quarterly: "par trimestre", annual: "par an" };
export const KIND_LABELS: Record<string, string> = { boost: "Remontée", featured: "À la une" };
export const LIMIT_LABELS: Record<keyof Limits, string> = {
  active_listings: "Annonces actives", agents: "Agents", photos_per_listing: "Photos par annonce", featured_credits: "Mises en avant incluses",
};
export const FEATURE_LABELS: Record<keyof Features, string> = {
  advanced_stats: "Statistiques avancées (non appliquée)", badge: "Badge (non appliqué)", enriched_agency_page: "Page agence enrichie (non appliquée)",
};

/** Champ numérique vide -> null (« à fixer ») ; texte invalide -> undefined (valeur ignorée, l'écran signale l'erreur). */
export function parseLimit(raw: string): number | null | undefined {
  const v = raw.trim();
  if (v === "") return null;
  return /^\d+$/.test(v) ? Number(v) : undefined;
}

export const fetchPlans = () => apiFetch<Data<AdminPlan[]>>("/admin/plans");
export const createPlan = (body: { code: string; name: string; audience: string; period: string }) => apiFetch<Data<AdminPlan>>("/admin/plans", { method: "POST", body });
export const updatePlan = (code: string, body: Record<string, unknown>) => apiFetch<Data<AdminPlan>>(`/admin/plans/${encodeURIComponent(code)}`, { method: "PATCH", body });
export const fetchProducts = () => apiFetch<Data<AdminProduct[]>>("/admin/premium-products");
export const updateProduct = (code: string, body: { price_xof?: number; is_active?: boolean }) => apiFetch<Data<unknown>>(`/admin/premium-products/${encodeURIComponent(code)}`, { method: "PATCH", body });
export const fetchInstructions = () => apiFetch<Data<PaymentInstructions>>("/admin/settings/payment-instructions");
export const saveInstructions = (body: NonNullable<PaymentInstructions>) => apiFetch<Data<unknown>>("/admin/settings/payment-instructions", { method: "PUT", body });
