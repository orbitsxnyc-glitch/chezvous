import type { Metadata } from "next";
import StayDetailView from "./StayDetailView";

export const metadata: Metadata = { title: "Mon séjour — ChezVous", robots: { index: false } };

export default async function Page(props: PageProps<"/mes-sejours/[id]">) {
  const { id } = await props.params;
  return <StayDetailView id={id} />;
}
// Espace derrière une connexion : pas de navigation instantanée à valider.
export const instant = false;
