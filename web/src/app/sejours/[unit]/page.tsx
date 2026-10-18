import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { connection } from "next/server";
import { Suspense } from "react";
import { SafeImage } from "@/components/SafeImage";
import { StayBooking } from "@/components/stays/StayBooking";
import { formatPrice } from "@/lib/format";
import { publicGet } from "@/lib/server-api";
import type { PublicUnit } from "@/lib/stays";

type Props = PageProps<"/sejours/[unit]">;

async function getUnit(id: string): Promise<PublicUnit | null> {
  const res = await publicGet<{ data: PublicUnit }>(`/stays/${encodeURIComponent(id)}`);
  return res?.data ?? null;
}

export async function generateMetadata(props: Props): Promise<Metadata> {
  const { unit } = await props.params;
  const u = await getUnit(unit);
  if (!u) notFound();
  return { title: `${u.name} — séjour à ${u.property.locality?.name ?? "Dakar"}`, description: u.rate ? formatPrice(u.rate.from_nightly_xof, "rent_furnished_short") : undefined, alternates: { canonical: `/sejours/${u.id}` } };
}

async function Detail({ params, searchParams }: { params: Props["params"]; searchParams: Props["searchParams"] }) {
  await connection();
  const { unit } = await params;
  const sp = await searchParams;
  const u = await getUnit(unit);
  if (!u) notFound();
  const first = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) ?? "";
  const p = u.property;
  const facts = [p.type, p.bedrooms !== null ? `${p.bedrooms} chambre${(p.bedrooms ?? 0) > 1 ? "s" : ""}` : null, p.bathrooms !== null ? `${p.bathrooms} salle${(p.bathrooms ?? 0) > 1 ? "s" : ""} de bain` : null, p.surface_m2 ? `${p.surface_m2} m²` : null, `${u.capacity_guests} voyageur${u.capacity_guests > 1 ? "s" : ""} max.`].filter(Boolean);

  return (
    <article className="flex flex-col gap-6">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted"><Link href="/" className="underline">Accueil</Link> <span aria-hidden>/</span> <Link href="/sejours" className="underline">Séjours</Link> <span aria-hidden>/</span> {u.name}</nav>
      <div className="grid gap-2 sm:grid-cols-[2fr_1fr]">
        <SafeImage src={p.photos[0]?.large ?? p.photos[0]?.medium ?? ""} alt={u.name} eager className="aspect-[4/3] w-full rounded-xl object-cover sm:row-span-2 sm:h-full" fallbackLabel="Pas de photo" />
        {p.photos.slice(1, 3).map((ph, i) => <SafeImage key={i} src={ph.medium ?? ph.thumb ?? ""} alt={`${u.name}, photo ${i + 2}`} className="hidden aspect-[4/3] w-full rounded-xl object-cover sm:block" />)}
      </div>
      <div className="grid items-start gap-8 lg:grid-cols-[1fr_22rem]">
        <div className="flex flex-col gap-5">
          <header>
            {p.locality && <p className="text-sm font-semibold text-muted">{p.locality.name}, Dakar</p>}
            <h1 className="mt-1 text-3xl font-extrabold">{u.name}</h1>
            <p className="mt-2 text-sm text-muted">{facts.join(" · ")}</p>
          </header>
          {p.amenities.length > 0 && (
            <section aria-labelledby="equipements">
              <h2 id="equipements" className="text-lg font-extrabold">Équipements</h2>
              <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">{p.amenities.map((a) => <li key={a}><span className="text-success" aria-hidden>✓</span> {a}</li>)}</ul>
            </section>
          )}
          <p className="rounded-lg bg-info-soft p-4 text-xs leading-relaxed text-[#194b82]">L&apos;adresse exacte est communiquée après confirmation du séjour. Les échanges se font par ChezVous : aucun numéro n&apos;est partagé avant.</p>
        </div>
        <StayBooking unitId={u.id} capacity={u.capacity_guests} minNights={u.rate?.min_nights ?? 1} initial={{ check_in: first(sp.check_in), check_out: first(sp.check_out), guests: first(sp.guests) || "2" }} nightly={u.rate?.from_nightly_xof ?? null} />
      </div>
    </article>
  );
}

export default function Page(props: Props) {
  return (
    <main className="mx-auto w-full max-w-6xl px-5 py-8">
      <Suspense fallback={<p>Chargement…</p>}><Detail params={props.params} searchParams={props.searchParams} /></Suspense>
    </main>
  );
}
