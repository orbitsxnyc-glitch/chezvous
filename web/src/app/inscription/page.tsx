import type { Metadata } from "next";
import RegisterForm from "./RegisterForm";

export const metadata: Metadata = { title: "Créer un compte — ChezVous" };

export default function Page() {
  return <RegisterForm />;
}
