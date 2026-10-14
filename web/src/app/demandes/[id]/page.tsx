import type { Metadata } from "next";
import { Suspense } from "react";
import InquiryThread from "@/components/inquiries/InquiryThread";

export const metadata: Metadata = { title: "Conversation — ChezVous", robots: { index: false } };

export default function Page(props: PageProps<"/demandes/[id]">) {
  return (
    <Suspense fallback={<p className="p-6">Chargement…</p>}>
      <InquiryThread params={props.params} />
    </Suspense>
  );
}
