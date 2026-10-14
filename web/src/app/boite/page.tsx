import type { Metadata } from "next";
import InquiryList from "@/components/inquiries/InquiryList";

export const metadata: Metadata = { title: "Demandes reçues — ChezVous", robots: { index: false } };

export default function Page() {
  return <InquiryList mode="inbox" />;
}
