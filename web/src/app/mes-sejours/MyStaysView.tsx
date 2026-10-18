"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { money } from "@/components/stays/StayCard";
import { BookingBadge } from "@/components/stays/BookingBadge";
import { useRequireAuth } from "@/components/useRequireAuth";
import { generalError } from "@/lib/forms";
import { fetchMyBookings, formatDay, type Booking } from "@/lib/stays";

export default function MyStaysView() {
  const { user, loading } = useRequireAuth();
  const [items, setItems] = useState<Booking[] | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    fetchMyBookings().then((r) => !cancelled && setItems(r.data)).catch((e) => !cancelled && setError(generalError(e)));
    return () => { cancelled = true; };
  }, [user]);

  return (
    <main className="mx-auto flex w-full max-w-4xl flex-col gap-4 px-5 py-8">
      <h1 className="text-3xl font-extrabold">Mes séjours</h1>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {(loading || items === null) && !error ? <p>Chargement…</p> : items && items.length === 0 ? (
        <p>Aucun séjour pour le moment. <Link href="/sejours" className="font-bold text-primary underline">Trouver un logement</Link></p>
      ) : (
        <ul className="flex flex-col gap-3">
          {items?.map((b) => (
            <li key={b.id}>
              <Link href={`/mes-sejours/${b.id}`} className="flex flex-col gap-1 rounded-xl border border-line bg-white p-4 shadow-card hover:border-[#e5b999]">
                <span className="flex flex-wrap items-center justify-between gap-2"><strong className="text-base">{b.unit.name}</strong><BookingBadge state={b.state} /></span>
                <span className="text-sm text-muted">{b.unit.locality ? `${b.unit.locality} · ` : ""}{formatDay(b.check_in)} → {formatDay(b.check_out)} · {b.guests} voyageur{b.guests > 1 ? "s" : ""}</span>
                <span className="text-sm font-bold text-primary">{money(b.total_xof)}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
