import type { Metadata } from "next";
import AgencyDashboard from "./AgencyDashboard";

export const metadata: Metadata = { title: "Espace agence — ChezVous", robots: { index: false } };

export default function Page() {
  return <AgencyDashboard />;
}
