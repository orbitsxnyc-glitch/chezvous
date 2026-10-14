import { apiFetch } from "./api";

export type AccountType = "individual" | "agency";
export type OtpPurpose = "verify_phone" | "password_reset";

export type User = {
  id: string;
  name: string;
  phone: string;
  email: string | null;
  whatsapp: string | null;
  account_type: AccountType;
  status: string;
  locale: string;
  city: string | null;
  phone_verified: boolean;
  roles: string[];
};

type Data<T> = { data: T };

export const registerAccount = (body: {
  name: string;
  phone: string;
  email?: string;
  password: string;
  account_type: AccountType;
}) => apiFetch<Data<{ message: string }>>("/auth/register", { method: "POST", body });

export const sendOtp = (phone: string, purpose: OtpPurpose) =>
  apiFetch<Data<{ message: string }>>("/auth/otp/send", { method: "POST", body: { phone, purpose } });

export const verifyOtp = (phone: string, code: string) =>
  apiFetch<Data<{ message: string }>>("/auth/otp/verify", { method: "POST", body: { phone, code } });

export const resetPassword = (body: { phone: string; code: string; password: string }) =>
  apiFetch<Data<{ message: string }>>("/auth/password/reset", { method: "POST", body });

export const login = (phone: string, password: string) =>
  apiFetch<Data<User>>("/auth/login", { method: "POST", body: { phone, password } });

export const logout = () => apiFetch<Data<{ message: string }>>("/auth/logout", { method: "POST" });

export const fetchMe = () => apiFetch<Data<User>>("/me");

export const updateMe = (body: Partial<Pick<User, "name" | "email" | "city" | "locale" | "whatsapp">> & {
  whatsapp_same_as_phone?: boolean;
}) => apiFetch<Data<User>>("/me", { method: "PATCH", body });

export const deleteMe = (password: string) => apiFetch<void>("/me", { method: "DELETE", body: { password } });
