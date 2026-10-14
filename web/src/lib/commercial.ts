import { apiFetch } from "./api";

type Data<T> = { data: T };

export type Limits = { active_listings: number | null; agents: number | null; photos_per_listing: number | null; featured_credits: number | null };
export type Plan = { code: string; name: string; audience: string; price_xof: number; period: string; limits: Limits; features: Record<string, boolean>; free: boolean };
export type Product = { code: string; kind: "featured" | "boost"; duration_days: number; price_xof: number };
export type Settlement = "unpaid" | "declared" | "confirmed" | "not_required";
export type PaymentInstructions = { methods: { method: string; label: string; details: string }[]; note?: string | null } | null;

export type SubscriptionView = {
  id: string;
  status: string;
  plan: { code: string; name: string; price_xof: number; period: string };
  payment_reference: string;
  settlement: Settlement;
  starts_at: string | null;
  ends_at: string | null;
  reason: string | null;
  downgrade_deadline_at: string | null;
  created_at: string;
  payments: { id: string; method: string; amount_xof: number; status: string; external_reference: string | null }[];
};

export type MySubscription = {
  entitlements: { plan: string; limits: Limits; features: Record<string, boolean>; free: boolean; ends_at: string | null };
  usage: { active_listings: number; limit: number };
  current: SubscriptionView | null;
  pending: SubscriptionView | null;
  history: SubscriptionView[];
  downgrade_deadline_at: string | null;
};

export type PremiumView = {
  id: string;
  kind: "featured" | "boost";
  status: string;
  source: "payment" | "credit";
  product: { code: string; duration_days: number; price_xof: number };
  listing: { id: string; reference: string; title: string | null };
  payment_reference: string | null;
  settlement: Settlement;
  ends_at: string | null;
};

/**
 * Libellé du statut de règlement. Règle produit : on n'écrit « réglé » que pour un encaissement CONFIRMÉ par un administrateur ;
 * une déclaration n'est jamais présentée comme un paiement reçu.
 */
export function settlementLabel(s: Settlement): string {
  switch (s) {
    case "confirmed": return "Règlement confirmé";
    case "declared": return "Règlement déclaré : en attente de confirmation";
    case "not_required": return "Inclus dans votre formule";
    default: return "Non réglé";
  }
}

export const PERIOD_LABELS: Record<string, string> = { monthly: "par mois", quarterly: "par trimestre", annual: "par an" };
export const METHOD_LABELS: Record<string, string> = { wave: "Wave", orange_money: "Orange Money", transfer: "Virement", cash: "Espèces", other: "Autre" };
export const KIND_LABELS: Record<string, string> = { boost: "Remontée (haut des résultats)", featured: "À la une (page d'accueil)" };

export const limitLabel = (v: number | null) => (v === null ? "à fixer" : String(v));

export const fetchMySubscription = () => apiFetch<Data<MySubscription> & { payment_instructions: PaymentInstructions }>("/my/subscription");
export const requestPlan = (plan: string) => apiFetch<Data<SubscriptionView> & { payment_instructions: PaymentInstructions }>("/my/subscription-requests", { method: "POST", body: { plan } });
export const cancelRequest = (id: string) => apiFetch<void>(`/my/subscription-requests/${id}`, { method: "DELETE" });
export const declareSubscriptionPayment = (id: string, form: FormData) => apiFetch<unknown>(`/my/subscription-requests/${id}/payments`, { method: "POST", body: form });
export const downgrade = (keep: string[]) => apiFetch<Data<{ kept: number; paused: number }>>("/my/subscription/downgrade", { method: "POST", body: { keep } });
export const fetchMyPremium = () => apiFetch<Data<PremiumView[]>>("/my/premium");
export const requestPremium = (listingUlid: string, product: string) =>
  apiFetch<Data<PremiumView> & { payment_instructions: PaymentInstructions }>(`/my/listings/${listingUlid}/premium`, { method: "POST", body: { product } });
export const cancelPremium = (id: string) => apiFetch<void>(`/my/premium/${id}`, { method: "DELETE" });
export const declarePremiumPayment = (id: string, form: FormData) => apiFetch<unknown>(`/my/premium/${id}/payments`, { method: "POST", body: form });
export const fetchPlans = () => apiFetch<Data<Plan[]>>("/plans");
export const fetchProducts = () => apiFetch<Data<Product[]>>("/premium-products");
export const fetchMyPublishedListings = () => apiFetch<{ data: { id: string; reference: string; title: string | null; status: string }[] }>("/my/listings?status=published");
