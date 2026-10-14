import type { Metadata } from "next";
import MyListingsView from "./MyListingsView";

export const metadata: Metadata = { title: "Mes annonces — ChezVous", robots: { index: false } };

export default function Page() {
  return <MyListingsView />;
}
