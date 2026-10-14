import type { Metadata } from "next";
import ResetForm from "./ResetForm";

export const metadata: Metadata = { title: "Mot de passe oublié — ChezVous" };

export default function Page() {
  return <ResetForm />;
}
