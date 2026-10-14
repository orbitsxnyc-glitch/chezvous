import type { Metadata } from "next";
import Image from "next/image";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { ResultsList } from "@/components/listings/ResultsList";
import { searchListings } from "@/lib/listings";
import { cacheLife } from "next/cache";
import { publicGet } from "@/lib/server-api";

type PublicAgency = {
  name: string;
  slug: string;
  description: string | null;
  address: string | null;
  locality: string | null;
  contact: { phone: string | null; email: string | null; whatsapp: string | null };
  logo_url: string | null;
  zones: { name: string; slug: string }[];
  verified: boolean;
  notice: string;
  active_listings: number;
};

async function getAgency(slug: string): Promise<PublicAgency | null> {
  "use cache";
  cacheLife("minutes");
  const res = await publicGet<{ data: PublicAgency }>(`/agencies/${encodeURIComponent(slug)}`);
  return res?.data ?? null;
}

type Props = PageProps<"/agences/[slug]">;

export async function generateMetadata(props: Props): Promise<Metadata> {
  const { slug } = await props.params;
  const agency = await getAgency(slug);
  if (!agency) notFound();
  const description = agency.description?.slice(0, 160) ?? `Biens de ${agency.name} sur ChezVous.`;
  return {
    title: `${agency.name}${agency.verified ? " · Agence vérifiée" : ""} — ChezVous`,
    description,
    alternates: { canonical: `/agences/${agency.slug}` },
    openGraph: { title: agency.name, description, url: `/agences/${agency.slug}`, type: "website", locale: "fr_SN", images: agency.logo_url ? [{ url: agency.logo_url }] : undefined },
  };
}

async function Content({ params }: { params: Props["params"] }) {
  const { slug } = await params;
  const agency = await getAgency(slug);
  if (!agency) notFound();
  const result = await searchListings({ agency: agency.slug });

  return (
    <>
      <header className="flex items-center gap-4">
        {agency.logo_url && <Image src={agency.logo_url} alt={`Logo de ${agency.name}`} width={96} height={96} unoptimized className="rounded" />}
        <div>
          <h1 className="text-2xl font-semibold">{agency.name}</h1>
          {agency.verified && <p className="text-sm text-green-900">✓ Agence vérifiée</p>}
          <p className="text-sm text-zinc-600">{[agency.address, agency.locality].filter(Boolean).join(" · ")}</p>
        </div>
      </header>
      {agency.description && <p className="whitespace-pre-line">{agency.description}</p>}
      <ul className="flex flex-wrap gap-x-4 text-sm">
        {agency.contact.phone && <li>Tél. {agency.contact.phone}</li>}
        {agency.contact.email && <li>{agency.contact.email}</li>}
      </ul>
      {agency.zones.length > 0 && <p className="text-sm">Zones couvertes : {agency.zones.map((z) => z.name).join(", ")}</p>}
      <p className="text-xs text-zinc-600">{agency.notice}</p>
      <h2 className="text-lg font-semibold">Biens de l&apos;agence ({agency.active_listings})</h2>
      {result ? <ResultsList result={result} params={{ agency: agency.slug }} basePath="/annonces" /> : <p role="alert">Annonces momentanément indisponibles.</p>}
    </>
  );
}

export default function Page(props: Props) {
  return (
    <main className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-6">
      <Suspense fallback={<p>Chargement…</p>}>
        <Content params={props.params} />
      </Suspense>
    </main>
  );
}
