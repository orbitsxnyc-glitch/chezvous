import type { Metadata } from "next";
import { Suspense } from "react";
import VerifyForm from "./VerifyForm";

export const metadata: Metadata = { title: "Vérifier mon compte — ChezVous" };

export default function Page() {
  return (
    <Suspense fallback={null}>
      <VerifyForm />
    </Suspense>
  );
}
