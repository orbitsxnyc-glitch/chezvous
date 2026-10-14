import type { Metadata } from "next";
import { Suspense } from "react";
import AcceptInvitation from "./AcceptInvitation";

// Le jeton est un secret : la page ne doit jamais être indexée ni mise en cache publique.
export const metadata: Metadata = { title: "Invitation — ChezVous", robots: { index: false, follow: false } };

export default function Page(props: PageProps<"/invitation/[token]">) {
  return (
    <Suspense fallback={<p className="p-6">Chargement…</p>}>
      <AcceptInvitation params={props.params} />
    </Suspense>
  );
}
