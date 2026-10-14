import type { Metadata } from "next";
import NewListingForm from "./NewListingForm";

export const metadata: Metadata = { title: "Nouvelle annonce — ChezVous", robots: { index: false } };

export default function Page() {
  return <NewListingForm />;
}
