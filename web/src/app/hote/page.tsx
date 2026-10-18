import type { Metadata } from "next";
import HostUnitsView from "./HostUnitsView";

export const metadata: Metadata = { title: "Espace hôte — ChezVous", robots: { index: false } };

export default function Page() {
  return <HostUnitsView />;
}
// Espace derrière une connexion : pas de navigation instantanée à valider.
export const instant = false;
