import { apiFetch } from "./api";

type Data<T> = { data: T };
type Page<T> = { data: T[]; meta?: { next_cursor?: string | null } };

export type PriceLine = { code: string; label: string; amount_xof: number };
export type Pricing =
  | { available: true; lines: PriceLine[]; total_xof: number; rules: { min_nights: number; taxes_notice: string } }
  | { available: false; reason: string; message: string };

export type PublicUnit = {
  id: string;
  name: string;
  capacity_guests: number;
  beds: number | null;
  property: {
    reference: string | null;
    type: string | null;
    locality: { name: string; slug: string } | null;
    bedrooms: number | null;
    bathrooms: number | null;
    surface_m2: number | null;
    amenities: string[];
    photos: Record<string, string>[];
  };
  rate: { currency: "XOF"; from_nightly_xof: number; min_nights: number; max_nights: number | null } | null;
  pricing?: { lines: PriceLine[]; total_xof: number } | Pricing;
};

export type BookingState =
  | "requested" | "accepted" | "awaiting_payment" | "confirmed" | "checked_in" | "completed"
  | "declined" | "expired" | "payment_failed" | "cancelled_by_guest" | "cancelled_by_host" | "disputed";

export const BOOKING_LABELS: Record<BookingState, string> = {
  requested: "Demande envoyée",
  accepted: "Acceptée : à régler",
  awaiting_payment: "En attente du règlement",
  confirmed: "Séjour confirmé",
  checked_in: "Séjour en cours",
  completed: "Séjour terminé",
  declined: "Refusée par l'hôte",
  expired: "Expirée",
  payment_failed: "Paiement échoué",
  cancelled_by_guest: "Annulée par le voyageur",
  cancelled_by_host: "Annulée par l'hôte",
  disputed: "Litige en cours",
};

export type BookingPayment = { id: string; status: string; method: string; reference: string } | null;
export type Booking = {
  id: string;
  state: BookingState;
  unit: { id: string; name: string; locality: string | null };
  guest: { first_name: string };
  check_in: string;
  check_out: string;
  guests: number;
  total_xof: number;
  currency: "XOF";
  lines?: PriceLine[];
  cancellation_policy: { code: string; tiers: { days_before: number; refund_bps: number }[] };
  respond_by: string | null;
  payment_due_at: string | null;
  reason: string | null;
  payment?: BookingPayment;
  timeline?: { to: BookingState; at: string }[];
};

export type PaymentIntent = {
  id: string;
  booking: string;
  status: string;
  method: string;
  amount_xof: number;
  reference: string;
  pay_before: string | null;
  declared_at: string | null;
  instructions: { methods: { method: string; label: string; details: string }[]; note: string | null } | null;
};

export type HostUnit = {
  id: string;
  name: string;
  status: "draft" | "active" | "paused" | "archived";
  capacity_guests: number;
  beds: number | null;
  property?: { reference: string | null; locality: string | null };
  approved?: boolean;
  photos?: { id: string; is_cover: boolean; status: string }[] | null;
  rate_plan?: { base_nightly_xof: number; min_nights: number; max_nights: number | null; cleaning_fee_xof: number } | null;
  calendar_blocks?: { id: number; starts_on: string; ends_on: string; kind: string; note: string | null }[];
};

export const UNIT_LABELS: Record<HostUnit["status"], string> = { draft: "Brouillon", active: "Active", paused: "En pause", archived: "Archivée" };

/** Paramètres de recherche : uniquement ceux qui sont renseignés. */
export function stayQuery(p: { check_in?: string; check_out?: string; guests?: string; locality?: string; price_max?: string }): string {
  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(p)) if (v) q.set(k, v);
  const s = q.toString();
  return s ? `?${s}` : "";
}

/** Nombre de nuits entre deux dates « AAAA-MM-JJ » (0 si invalide ou inversé). */
export function nightsBetween(checkIn: string, checkOut: string): number {
  const a = Date.parse(`${checkIn}T00:00:00Z`);
  const b = Date.parse(`${checkOut}T00:00:00Z`);
  if (Number.isNaN(a) || Number.isNaN(b) || b <= a) return 0;
  return Math.round((b - a) / 86_400_000);
}

export const formatDay = (iso: string) => new Date(`${iso}T00:00:00Z`).toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" });

/** Un séjour attend-il une action du voyageur ? (règlement à préparer ou à déclarer) */
export const needsPayment = (b: Pick<Booking, "state">) => b.state === "accepted" || b.state === "awaiting_payment";

export const fetchStay = (id: string, params: { check_in?: string; check_out?: string; guests?: string }) =>
  apiFetch<Data<PublicUnit & { pricing?: Pricing }>>(`/stays/${encodeURIComponent(id)}${stayQuery(params)}`);

export const createQuote = (body: { unit: string; check_in: string; check_out: string; guests: number }) =>
  apiFetch<Data<{ id: string; total_xof: number }>>("/quotes", { method: "POST", body });
export const requestBooking = (body: { quote: string; idempotency_key: string }) => apiFetch<Data<Booking>>("/booking-requests", { method: "POST", body });

export const fetchMyBookings = (cursor?: string) => apiFetch<Page<Booking>>(`/my/bookings${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ""}`);
export const fetchMyBooking = (id: string) => apiFetch<Data<Booking>>(`/my/bookings/${id}`);
export const cancelMyBooking = (id: string, reason?: string) => apiFetch<Data<Booking>>(`/my/bookings/${id}/cancel`, { method: "POST", body: { reason } });
export const createPaymentIntent = (bookingId: string, method: string) => apiFetch<Data<PaymentIntent>>(`/my/bookings/${bookingId}/payment-intents`, { method: "POST", body: { method } });
export const declarePayment = (intentId: string, reference: string) => apiFetch<Data<PaymentIntent>>(`/my/payment-intents/${intentId}/declare`, { method: "POST", body: { reference } });

export const fetchHostBookings = (state?: string) => apiFetch<Page<Booking>>(`/host/bookings${state ? `?state=${state}` : ""}`);
export const hostBookingAction = (id: string, action: "accept" | "decline" | "cancel", reason?: string) =>
  apiFetch<Data<Booking>>(`/host/bookings/${id}/${action}`, { method: "POST", body: reason ? { reason } : {} });

export const fetchHostUnits = () => apiFetch<Page<HostUnit>>("/host/units");
export const fetchHostUnit = (id: string) => apiFetch<Data<HostUnit>>(`/host/units/${id}`);
export const createHostUnit = (body: { name: string; capacity_guests: number; beds?: number; property_type_id: number; locality_id?: number }) => apiFetch<Data<HostUnit>>("/host/units", { method: "POST", body });
export const setRatePlan = (id: string, body: { base_nightly_xof: number; min_nights?: number; max_nights?: number | null; cleaning_fee_xof?: number }) => apiFetch<Data<HostUnit>>(`/host/units/${id}/rate-plan`, { method: "PUT", body });
export const uploadUnitPhotos = (id: string, files: File[]) => {
  const body = new FormData();
  files.forEach((f) => body.append("images[]", f));
  return apiFetch<Data<HostUnit>>(`/host/units/${id}/images`, { method: "POST", body });
};
export const setUnitCover = (id: string, imageId: string) => apiFetch<Data<HostUnit>>(`/host/units/${id}/images/${imageId}/cover`, { method: "PATCH" });
export const removeUnitPhoto = (id: string, imageId: string) => apiFetch<void>(`/host/units/${id}/images/${imageId}`, { method: "DELETE" });
export const addRateOverride = (id: string, body: { starts_on: string; ends_on: string; nightly_xof: number; min_nights?: number; priority?: number }) => apiFetch<unknown>(`/host/units/${id}/rate-overrides`, { method: "POST", body });
export const setUnitStatus = (id: string, status: HostUnit["status"]) => apiFetch<Data<HostUnit>>(`/host/units/${id}/status`, { method: "POST", body: { status } });
export const blockDates = (id: string, body: { starts_on: string; ends_on: string; note?: string }) => apiFetch<unknown>(`/host/units/${id}/calendar-blocks`, { method: "POST", body });
export const unblockDates = (id: string, blockId: number) => apiFetch<void>(`/host/units/${id}/calendar-blocks/${blockId}`, { method: "DELETE" });

export type AdminIntent = { id: string; booking: string; guest_first_name: string; method: string; amount_xof: number; reference: string; declared_reference: string | null; declared_at: string | null; status: string };
export type AdminRefund = { id: string; booking: string; reason: string; amount_xof: number; status: string; method: string; payment_reference: string };
export const fetchAdminIntents = (status = "pending") => apiFetch<{ data: AdminIntent[] }>(`/admin/payment-intents?status=${status}`);
export const confirmIntent = (id: string, amount_xof: number) => apiFetch<unknown>(`/admin/payment-intents/${id}/confirm`, { method: "POST", body: { amount_xof } });
export const rejectIntent = (id: string, reason: string) => apiFetch<unknown>(`/admin/payment-intents/${id}/reject`, { method: "POST", body: { reason } });
export const fetchAdminRefunds = () => apiFetch<{ data: AdminRefund[] }>("/admin/refunds");
export const confirmRefund = (id: string, reference: string) => apiFetch<unknown>(`/admin/refunds/${id}/confirm`, { method: "POST", body: { reference } });
export const failRefund = (id: string, reason: string) => apiFetch<unknown>(`/admin/refunds/${id}/fail`, { method: "POST", body: { reason } });
export const fetchPendingUnits = () => apiFetch<Page<HostUnit>>("/admin/units/pending");
export const approveUnit = (id: string) => apiFetch<unknown>(`/admin/units/${id}/approve`, { method: "POST" });
