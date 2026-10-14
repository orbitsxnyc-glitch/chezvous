import type { Metadata } from "next";
import ProfileView from "./ProfileView";

export const metadata: Metadata = { title: "Mon profil — ChezVous" };

export default function Page() {
  return <ProfileView />;
}
