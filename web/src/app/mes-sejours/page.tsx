import type { Metadata } from "next";
import MyStaysView from "./MyStaysView";

export const metadata: Metadata = { title: "Mes séjours — ChezVous", robots: { index: false } };

export default function Page() {
  return <MyStaysView />;
}
// Espace derrière une connexion : pas de navigation instantanée à valider.
export const instant = false;
