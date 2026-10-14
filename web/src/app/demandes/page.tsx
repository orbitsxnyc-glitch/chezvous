import type { Metadata } from "next";
import InquiryList from "@/components/inquiries/InquiryList";

export const metadata: Metadata = { title: "Mes demandes — ChezVous", robots: { index: false } };

export default function Page() {
  return <InquiryList mode="sent" />;
}
