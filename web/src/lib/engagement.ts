import { apiFetch } from "./api";
import type { ListingSummary } from "./listings";

type Data<T> = { data: T };
type Page<T> = { data: T[]; meta?: { next_cursor?: string | null } };

export type InquiryStatus = "new" | "in_progress" | "visit_scheduled" | "concluded" | "lost" | "closed";

export const STATUS_LABELS: Record<InquiryStatus, string> = {
  new: "Nouvelle",
  in_progress: "En cours",
  visit_scheduled: "Visite planifiée",
  concluded: "Conclue",
  lost: "Perdue",
  closed: "Fermée",
};

export type Visit = {
  id: string;
  status: "proposed" | "confirmed" | "rescheduled" | "cancelled" | "done";
  proposed_slots: string[];
  scheduled_at: string | null;
  proposed_by_me: boolean;
  awaiting_me: boolean;
  cancel_reason: string | null;
  feedback: { available: boolean; conform: boolean; comment: string | null } | null;
};

export type InquirySummary = {
  id: string;
  role: "requester" | "owner";
  status: InquiryStatus;
  listing: { id: string; reference: string; title: string | null };
  preferred_channel: string;
  last_activity_at: string;
  unread: number;
  requester?: { name: string; phone: string };
  advertiser?: { name: string };
};

export type InquiryDetail = InquirySummary & {
  can_respond?: boolean;
  messages: { id: number; mine: boolean; body: string; created_at: string; from: "requester" | "owner"; sender_name: string | null }[];
  visits: Visit[];
  notes?: { id: number; body: string; author: string; created_at: string }[];
};

export type AppNotification = { id: string; type: string; data: Record<string, unknown>; read: boolean; created_at: string };
export type SavedSearch = { id: string; name: string; criteria: Record<string, string | number | boolean>; frequency: "immediate" | "daily"; is_active: boolean };
export type FavoriteItem = { listing: ListingSummary; available: boolean; added_at: string };

const qs = (cursor?: string) => (cursor ? `?cursor=${encodeURIComponent(cursor)}` : "");

export const createInquiry = (listingUlid: string, body: { message: string; phone?: string; preferred_channel: string }) =>
  apiFetch<Data<InquiryDetail>>(`/listings/${encodeURIComponent(listingUlid)}/inquiries`, { method: "POST", body });
export const fetchSent = (cursor?: string) => apiFetch<Page<InquirySummary>>(`/my/inquiries${qs(cursor)}`);
export const fetchInbox = (cursor?: string) => apiFetch<Page<InquirySummary>>(`/inbox${qs(cursor)}`);
export const fetchInquiry = (id: string) => apiFetch<Data<InquiryDetail>>(`/inquiries/${id}`);
export const sendMessage = (id: string, body: string) => apiFetch<unknown>(`/inquiries/${id}/messages`, { method: "POST", body: { body } });
export const addNote = (id: string, body: string) => apiFetch<unknown>(`/inquiries/${id}/notes`, { method: "POST", body: { body } });
export const setInquiryStatus = (id: string, status: "in_progress" | "concluded" | "lost" | "closed") =>
  apiFetch<Data<InquiryDetail>>(`/inquiries/${id}`, { method: "PATCH", body: { status } });
export const proposeVisit = (id: string, slots: string[]) => apiFetch<Data<Visit>>(`/inquiries/${id}/visits`, { method: "POST", body: { slots } });
export const visitAction = (id: string, body: { action: "confirm" | "reschedule" | "cancel" | "done"; slot?: string; slots?: string[]; reason?: string }) =>
  apiFetch<Data<Visit>>(`/visits/${id}`, { method: "PATCH", body });
export const visitFeedback = (id: string, body: { available: boolean; conform: boolean; comment?: string }) =>
  apiFetch<Data<Visit>>(`/visits/${id}/feedback`, { method: "POST", body });

export const fetchFavorites = () => apiFetch<Data<FavoriteItem[]>>("/my/favorites");
export const addFavorite = (listing: string) => apiFetch<unknown>("/my/favorites", { method: "POST", body: { listing } });
export const removeFavorite = (listing: string) => apiFetch<void>(`/my/favorites/${encodeURIComponent(listing)}`, { method: "DELETE" });

export const fetchSavedSearches = () => apiFetch<Data<SavedSearch[]>>("/my/saved-searches");
export const createSavedSearch = (body: { name: string; criteria: Record<string, string>; frequency: "immediate" | "daily" }) =>
  apiFetch<Data<SavedSearch>>("/my/saved-searches", { method: "POST", body });
export const updateSavedSearch = (id: string, body: { is_active?: boolean; frequency?: "immediate" | "daily"; name?: string }) =>
  apiFetch<Data<SavedSearch>>(`/my/saved-searches/${id}`, { method: "PATCH", body });
export const deleteSavedSearch = (id: string) => apiFetch<void>(`/my/saved-searches/${id}`, { method: "DELETE" });

export const fetchNotifications = (cursor?: string) =>
  apiFetch<{ data: AppNotification[]; meta: { next_cursor: string | null; unread: number } }>(`/my/notifications${qs(cursor)}`);
export const markNotificationsRead = (ids?: string[]) => apiFetch<unknown>("/my/notifications/read", { method: "PATCH", body: ids ? { ids } : {} });

/** Valeur d'un champ datetime-local (heure locale du navigateur) -> ISO 8601 UTC. */
export function localToIso(value: string): string {
  return new Date(value).toISOString();
}

export const formatDateTime = (iso: string) => new Date(iso).toLocaleString("fr-FR", { dateStyle: "full", timeStyle: "short" });

/** Phrase lisible pour une notification (les données ne contiennent jamais de coordonnées personnelles). */
export function describeNotification(n: AppNotification): string {
  const ref = typeof n.data.listing === "string" ? ` (${n.data.listing})` : "";
  switch (n.type) {
    case "inquiry.new": return `Nouvelle demande de contact${ref}`;
    case "inquiry.message": return `Nouveau message${ref}`;
    case "inquiry.status": return `Une demande a changé de statut${ref}`;
    case "inquiry.assigned": return `Une demande vous a été attribuée${ref}`;
    case "inquiry.unanswered": return `Demande sans réponse depuis plus de 48 h${ref}`;
    case "visit.proposed": return `Nouvelle proposition de visite${ref}`;
    case "visit.confirmed": return `Visite confirmée${ref}`;
    case "visit.rescheduled": return `Visite à reprogrammer${ref}`;
    case "visit.cancelled": return `Visite annulée${ref}`;
    case "visit.reminder": return `Rappel : visite dans ${String(n.data.hours_before ?? "")} h${ref}`;
    case "visit.done": return `Visite effectuée : donnez votre retour${ref}`;
    case "visit.feedback": return `Retour reçu après une visite${ref}`;
    case "favorite.price_drop": return `Baisse de prix sur un favori${ref}`;
    case "favorite.unavailable": return `Un favori n'est plus disponible${ref}`;
    case "search.alert": return `${String(n.data.count ?? "")} nouvelle(s) annonce(s) pour « ${String(n.data.name ?? "")} »`;
    default: return "Notification";
  }
}

/** Lien associé à une notification, s'il existe. */
export function notificationHref(n: AppNotification): string | null {
  if (typeof n.data.inquiry === "string") return `/demandes/${n.data.inquiry}`;
  if (typeof n.data.listing === "string") return `/annonces/${n.data.listing}`;
  return null;
}
