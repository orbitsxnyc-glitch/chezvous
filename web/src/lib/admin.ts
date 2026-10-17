import { apiFetch } from "./api";

type Data<T> = { data: T };
type Page<T> = { data: T[]; meta: { next_cursor: string | null } };

export type TwoFactorStatus = { required: boolean; enabled: boolean; verified: boolean; recovery_codes_left: number };

export const twoFactorStatus = () => apiFetch<Data<TwoFactorStatus>>("/auth/2fa/status");
export const twoFactorSetup = () => apiFetch<Data<{ secret: string; otpauth_uri: string }>>("/auth/2fa/setup", { method: "POST" });
export const twoFactorConfirm = (code: string) =>
  apiFetch<Data<{ recovery_codes: string[]; notice: string }>>("/auth/2fa/confirm", { method: "POST", body: { code } });
export const twoFactorVerify = (body: { code?: string; recovery_code?: string }) =>
  apiFetch<Data<{ verified: boolean; recovery_codes_left: number }>>("/auth/2fa/verify", { method: "POST", body });

export const isAdmin = (roles: string[] | undefined) => !!roles && (roles.includes("admin") || roles.includes("super_admin"));

export type Dashboard = {
  registrations: { today: number; last_7_days: number; last_30_days: number; total: number };
  listings: { published: number; pending_moderation: number; published_last_30_days: number };
  moderation: { open_reports: number; pending_agency_verifications: number };
  inquiries: { sent_last_30_days: number; open: number };
  commercial: {
    active_subscriptions: number; active_premium: number; pending_requests: number;
    confirmed_revenue_month_xof: number; declared_unconfirmed_xof: number; declared_unconfirmed_count: number;
  };
  notifications: { failed_last_24h: number };
};
export const fetchDashboard = () => apiFetch<Data<Dashboard>>("/admin/dashboard");

export type AdminReport = {
  id: number; status: string; action: string | null; created_at: string; comment: string | null; reporter: string;
  reason: { code: string; label: string };
  listing: { id: string; reference: string; title: string | null; status: string };
  open_reports_on_listing: number;
};
export const fetchReports = (status = "open", cursor?: string) =>
  apiFetch<Page<AdminReport>>(`/admin/reports?status=${status}${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ""}`);
export type ReportAction = "hide" | "warn" | "suspend-advertiser" | "dismiss";
export const actOnReport = (id: number, action: ReportAction, note: string) =>
  apiFetch<unknown>(`/admin/reports/${id}/${action}`, { method: "POST", body: { note } });

export type AdminUser = {
  id: string; name: string; phone: string; email: string | null; account_type: string; status: string; roles: string[];
  phone_verified: boolean; created_at: string; agency: { name: string; role: string } | null;
};
export const fetchUsers = (q: string, status: string, cursor?: string) => {
  const p = new URLSearchParams();
  if (q) p.set("q", q);
  if (status) p.set("status", status);
  if (cursor) p.set("cursor", cursor);
  return apiFetch<Page<AdminUser>>(`/admin/users?${p}`);
};
export const suspendUser = (id: string, reason: string) =>
  apiFetch<Data<AdminUser>>(`/admin/users/${id}/suspend`, { method: "POST", body: { reason } });
export const reactivateUser = (id: string) => apiFetch<Data<AdminUser>>(`/admin/users/${id}/reactivate`, { method: "POST" });

export type Delivery = {
  id: number; type: string; channel: string; status: string; provider: string | null; attempts: number;
  error: string | null; created_at: string; delivered: boolean;
};
export const fetchDeliveries = (status: string, cursor?: string) => {
  const p = new URLSearchParams();
  if (status) p.set("status", status);
  if (cursor) p.set("cursor", cursor);
  return apiFetch<Page<Delivery> & { meta: { counts: Record<string, number> } }>(`/admin/notification-deliveries?${p}`);
};
export const retryDelivery = (id: number) => apiFetch<unknown>(`/admin/notification-deliveries/${id}/retry`, { method: "POST" });

// ---- Modération des annonces (file d'attente) ----
import type { MyListing } from "./myListings";

export type ModerationListing = MyListing;
export type RejectionReason = { code: string; label: string };

export const fetchModerationQueue = (cursor?: string) =>
  apiFetch<Page<ModerationListing>>(`/admin/moderation${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ""}`);
export const fetchRejectionReasons = () => apiFetch<Data<RejectionReason[]>>("/admin/moderation/reasons");
export const approveListing = (id: string) => apiFetch<Data<ModerationListing>>(`/admin/listings/${encodeURIComponent(id)}/approve`, { method: "POST" });
export const rejectListing = (id: string, reasonCode: string, comment: string) =>
  apiFetch<Data<ModerationListing>>(`/admin/listings/${encodeURIComponent(id)}/reject`, {
    method: "POST",
    body: { reason_code: reasonCode, ...(comment.trim() ? { comment: comment.trim() } : {}) },
  });

/** Libellé d'un référentiel par identifiant (type de bien, quartier) ; l'API de modération ne renvoie que les identifiants. */
export function labelById(items: { id: number; label?: string; name?: string }[], id: number | null): string | null {
  if (id === null) return null;
  const found = items.find((i) => i.id === id);
  return found ? (found.label ?? found.name ?? null) : null;
}
