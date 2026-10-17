import { Suspense } from "react";
import PassportAdminView from "./PassportAdminView";

export default function Page() {
  return (
    <Suspense fallback={<p>Chargement…</p>}>
      <PassportAdminView />
    </Suspense>
  );
}
