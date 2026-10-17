import type { Metadata } from "next";
import type { ReactNode } from "react";
import AdminShell from "@/components/admin/AdminShell";

// L'espace d'administration est entièrement derrière une connexion : pas de navigation instantanée à valider.
export const instant = false;

export const metadata: Metadata = { title: "Administration — ChezVous", robots: { index: false, follow: false } };

export default function AdminLayout({ children }: { children: ReactNode }) {
  return <AdminShell>{children}</AdminShell>;
}
