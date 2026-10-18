import type { Metadata } from "next";
import HostBookingsView from "./HostBookingsView";

export const metadata: Metadata = { title: "Demandes de séjour — ChezVous", robots: { index: false } };

export default function Page() {
  return <HostBookingsView />;
}
// Espace derrière une connexion : pas de navigation instantanée à valider.
export const instant = false;
