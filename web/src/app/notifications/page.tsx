import type { Metadata } from "next";
import NotificationsView from "./NotificationsView";

export const metadata: Metadata = { title: "Notifications — ChezVous", robots: { index: false } };

export default function Page() {
  return <NotificationsView />;
}
