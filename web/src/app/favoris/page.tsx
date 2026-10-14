import type { Metadata } from "next";
import FavoritesView from "./FavoritesView";

export const metadata: Metadata = { title: "Mes favoris — ChezVous", robots: { index: false } };

export default function Page() {
  return <FavoritesView />;
}
