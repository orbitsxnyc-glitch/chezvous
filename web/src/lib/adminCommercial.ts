import { apiFetch } from "./api";

type Data<T> = { data: T };
type Page<T> = { data: T[]; meta: { next_cursor: string | null } };

export type PaymentRow = {
  id: string;
  method: string;
  amount_xof: number;
  status: "declared" | "confirmed" | "rejected";
  external_reference: string | null;
  received_at?: string | null;
  has_proof: boolean;
  proof_url: string | null;
};

export type Settlement = "unpaid" | "declared" | "confirmed" | "not_required";

export type AdminSubscription = {
  id: string;
  status: string;
  plan: { code: string; name: string; price_xof: number; period: string };
  payment_reference: string;
  subscriber: { type: "user" | "agency"; name: string; requested_by: string };
  settlement: Settlement;
  starts_at: string | null;
  ends_at: string | null;
  reason: string | null;
  created_at: string;
  payments: PaymentRow[];
};

export type AdminPremium = {
  id: string;
  kind: string;
  status: string;
  source: string;
  product: { code: string; duration_days: number; price_xof: number };
  listing: { id: string; reference: string; title: string | null };
  advertiser: string;
  payment_reference: string;
  settlement: Settlement;
  starts_at: string | null;
  ends_at: string | null;
  reason: string | null;
  payments: PaymentRow[];
};

export const STATUS_LABELS: Record<string, string> = {
  pending: "En attente", active: "Active", suspended: "Suspendue", cancelled: "Annulée", expired: "Expirée", replaced: "Remplacée",
};
export const SETTLEMENT_LABELS: Record<Settlement, string> = {
  unpaid: "Non réglé", declared: "Déclaré, non confirmé", confirmed: "Encaissement confirmé", not_required: "Sans règlement (crédit inclus)",
};
export const METHOD_LABELS: Record<string, string> = {
  wave: "Wave", orange_money: "Orange Money", transfer: "Virement", cash: "Espèces", other: "Autre",
};
export const PAYMENT_LABELS: Record<PaymentRow["status"], string> = { declared: "Déclaré", confirmed: "Confirmé", rejected: "Rejeté" };

/** « Activer » n'est proposé que si un encaissement est CONFIRMÉ ; l'API le refuse de toute façon sinon. */
export const canActivate = (status: string, settlement: Settlement) => status === "pending" && (settlement === "confirmed" || settlement === "not_required");

const q = (status: string, cursor?: string) => {
  const p = new URLSearchParams();
  if (status) p.set("status", status);
  if (cursor) p.set("cursor", cursor);
  const s = p.toString();
  return s ? `?${s}` : "";
};

export const fetchSubscriptions = (status: string, cursor?: string) => apiFetch<Page<AdminSubscription>>(`/admin/subscriptions${q(status, cursor)}`);
export const fetchPremium = (status: string, cursor?: string) => apiFetch<Page<AdminPremium>>(`/admin/premium${q(status, cursor)}`);

export const confirmPayment = (id: string) => apiFetch<Data<unknown>>(`/admin/payments/${id}/confirm`, { method: "POST" });
export const rejectPayment = (id: string, note: string) => apiFetch<Data<unknown>>(`/admin/payments/${id}/reject`, { method: "POST", body: { note } });

export type Kind = "subscriptions" | "premium";
export const lifecycle = (kind: Kind, id: string, action: "activate" | "suspend" | "reinstate" | "cancel", reason?: string) =>
  apiFetch<unknown>(`/admin/${kind}/${id}/${action}`, { method: "POST", ...(reason ? { body: { reason } } : {}) });

export function recordPayment(kind: Kind, id: string, form: FormData) {
  return apiFetch<Data<unknown>>(`/admin/${kind}/${id}/payments`, { method: "POST", body: form });
}
