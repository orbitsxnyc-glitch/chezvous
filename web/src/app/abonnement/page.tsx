import type { Metadata } from "next";
import SubscriptionView from "./SubscriptionView";

export const metadata: Metadata = { title: "Mon abonnement — ChezVous", robots: { index: false } };

export default function Page() {
  return <SubscriptionView />;
}
