import type { Metadata } from "next";
import AlertsView from "./AlertsView";

export const metadata: Metadata = { title: "Mes alertes — ChezVous", robots: { index: false } };

export default function Page() {
  return <AlertsView />;
}
