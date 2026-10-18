import type { Metadata } from "next";
import HostUnitView from "./HostUnitView";

export const metadata: Metadata = { title: "Logement — Espace hôte", robots: { index: false } };

export default async function Page(props: PageProps<"/hote/unites/[id]">) {
  const { id } = await props.params;
  return <HostUnitView id={id} />;
}
// Espace derrière une connexion : pas de navigation instantanée à valider.
export const instant = false;
