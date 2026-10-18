import { BOOKING_LABELS, type BookingState } from "@/lib/stays";

const TONE: Partial<Record<BookingState, string>> = {
  confirmed: "bg-success-soft text-[#0b6240]",
  checked_in: "bg-success-soft text-[#0b6240]",
  completed: "bg-[#f1ece6] text-[#5a4f47]",
  requested: "bg-warning-soft text-[#714105]",
  accepted: "bg-info-soft text-[#194b82]",
  awaiting_payment: "bg-info-soft text-[#194b82]",
  declined: "bg-[#fdecea] text-[#8b1c1c]",
  payment_failed: "bg-[#fdecea] text-[#8b1c1c]",
  disputed: "bg-[#fdecea] text-[#8b1c1c]",
};

/** L'état est écrit en toutes lettres : la couleur n'est jamais la seule information. */
export function BookingBadge({ state }: { state: BookingState }) {
  return <span className={`inline-flex w-fit rounded-md px-2.5 py-1.5 text-xs font-bold ${TONE[state] ?? "bg-[#f1ece6] text-[#5a4f47]"}`}>{BOOKING_LABELS[state]}</span>;
}
