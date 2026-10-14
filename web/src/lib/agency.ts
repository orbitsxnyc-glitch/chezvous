import { apiFetch } from "./api";

export type Agency = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  address: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  verified: boolean;
  status: string;
  verification: { status: "pending" | "approved" | "rejected"; note: string | null } | null;
  me: { role: "manager" | "agent"; can_see_all: boolean } | null;
};

export type Member = { id: string; name: string; role: "manager" | "agent"; status?: string; phone?: string; can_see_all?: boolean };

export type Invitation = { id: string; phone: string | null; email: string | null; status: string; expires_at: string };

export type AgencyStats = {
  scope: "agency" | "self";
  totals: { active_listings: number; pending_listings: number; views: number; contacts: number };
  quota: { limit: number; used: number } | null;
  agents: { member_id: string; name: string; role: string; active_listings: number; pending_listings: number; views: number; contacts: number }[];
  note: string;
};

type Data<T> = { data: T };

export const fetchAgency = () => apiFetch<Data<Agency>>("/agency");
export const fetchMembers = () => apiFetch<Data<Member[]>>("/agency/members");
export const fetchInvitations = () => apiFetch<Data<Invitation[]>>("/agency/invitations");
export const fetchStats = () => apiFetch<Data<AgencyStats>>("/agency/stats");
export const inviteMember = (body: { phone?: string; email?: string; can_see_all?: boolean }) =>
  apiFetch<Data<{ id: string }>>("/agency/invitations", { method: "POST", body });
export const revokeInvitation = (id: string) => apiFetch<void>(`/agency/invitations/${id}`, { method: "DELETE" });
export const updateMember = (id: string, body: { status?: "active" | "suspended"; can_see_all?: boolean }) =>
  apiFetch<Data<{ id: string; status: string; can_see_all: boolean }>>(`/agency/members/${id}`, { method: "PATCH", body });
export const acceptInvitation = (token: string) =>
  apiFetch<Data<{ agency: string; role: string }>>("/agency/invitations/accept", { method: "POST", body: { token } });
