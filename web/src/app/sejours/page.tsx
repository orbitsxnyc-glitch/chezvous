import type { Metadata } from "next";
import Link from "next/link";
import { connection } from "next/server";
import { Suspense } from "react";
import { StayCard } from "@/components/stays/StayCard";
import { getNeighborhoods } from "@/lib/listings";
import { publicGet } from "@/lib/server-api";
import { stayQuery, type PublicUnit } from "@/lib/stays";

export const metadata: Metadata = {
  title: "Séjours meublés à Dakar — ChezVous",
  description: "Appartements et villas meublés à Dakar : prix total transparent, disponibilité contrôlée avant toute demande.",
  alternates: { canonical: "/sejours" },
};

const pick = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v) ?? "";
const control = "min-h-12 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink";
const label = "text-xs font-bold text-[#3f362f]";

async function Results({ searchParams }: { searchParams: PageProps<"/sejours">["searchParams"] }) {
  await connection(); // les disponibilités dépendent de la date du jour
  const raw = await searchParams;
  const p = { check_in: pick(raw.check_in), check_out: pick(raw.check_out), guests: pick(raw.guests), locality: pick(raw.locality), price_max: pick(raw.price_max) };
  const neighborhoods = await getNeighborhoods().catch(() => []);
  const params = Object.fromEntries(Object.entries(p).filter(([, v]) => v));
  const res = await publicGet<{ data: PublicUnit[] }>("/stays", params).catch(() => undefined);
  const dated = Boolean(p.check_in && p.check_out);
  const query = stayQuery({ check_in: p.check_in, check_out: p.check_out, guests: p.guests });
  const today = new Date().toISOString().slice(0, 10);

  return (
    <>
      <form action="/sejours" method="get" role="search" className="grid items-end gap-3 rounded-2xl border border-primary/10 bg-white p-4 shadow-panel sm:grid-cols-2 lg:grid-cols-[1fr_1fr_0.7fr_1fr_1fr_auto]">
        <label className="flex flex-col gap-1.5"><span className={label}>Arrivée</span><input type="date" name="check_in" min={today} defaultValue={p.check_in} className={control} /></label>
        <label className="flex flex-col gap-1.5"><span className={label}>Départ</span><input type="date" name="check_out" min={today} defaultValue={p.check_out} className={control} /></label>
        <label className="flex flex-col gap-1.5"><span className={label}>Voyageurs</span><input type="number" name="guests" min={1} max={60} defaultValue={p.guests || "2"} className={control} /></label>
        <label className="flex flex-col gap-1.5">
          <span className={label}>Quartier</span>
          <select name="locality" defaultValue={p.locality} className={control}>
            <option value="">Tous les quartiers</option>
            {neighborhoods.map((n) => <option key={n.slug} value={n.slug}>{n.name}</option>)}
          </select>
        </label>
        <label className="flex flex-col gap-1.5"><span className={label}>Budget max / nuit</span><input type="number" name="price_max" min={1} step={1000} placeholder="Illimité" defaultValue={p.price_max} className={control} /></label>
        <button type="submit" className="min-h-12 rounded-lg bg-primary px-6 text-sm font-bold text-white shadow-card hover:bg-primary-hover">Rechercher</button>
      </form>
      {res === undefined ? (
        <p role="alert" className="rounded-xl border border-line bg-white p-6">Les séjours sont momentanément indisponibles. Réessayez dans un instant.</p>
      ) : res === null || res.data.length === 0 ? (
        <p className="rounded-xl border border-line bg-white p-6">Aucun séjour ne correspond{dated ? " à ces dates" : ""}. Essayez d&apos;autres dates ou un autre quartier.</p>
      ) : (
        <>
          <p className="text-sm font-bold">{res.data.length} logement{res.data.length > 1 ? "s" : ""} {dated ? "disponible" + (res.data.length > 1 ? "s" : "") + " sur ces dates" : "à découvrir"}</p>
          <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {res.data.map((u) => <li key={u.id}><StayCard unit={u} query={query} /></li>)}
          </ul>
          <p className="text-xs text-muted">La disponibilité affichée est indicative : un séjour n&apos;est réservé qu&apos;après acceptation de l&apos;hôte puis règlement.</p>
        </>
      )}
    </>
  );
}

export default function Page(props: PageProps<"/sejours">) {
  return (
    <main className="mx-auto flex w-full max-w-6xl flex-col gap-5 px-5 py-8">
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted"><Link href="/" className="underline">Accueil</Link> <span aria-hidden>/</span> Séjours</nav>
      <h1 className="text-3xl font-extrabold">Séjours meublés à Dakar</h1>
      <Suspense fallback={<p>Chargement…</p>}>
        <Results searchParams={props.searchParams} />
      </Suspense>
    </main>
  );
}
