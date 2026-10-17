import { Suspense } from "react";
import PassportAdminView from "./PassportAdminView";

export default function Page() {
  return (
    <Suspense fallback={<p>Chargement…</p>}>
      <PassportAdminView />
    </Suspense>
  );
}

// Espace derrière une connexion : pas de navigation instantanée à valider.
export const instant = false;
