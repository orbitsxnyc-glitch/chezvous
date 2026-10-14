import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { ContactPanel } from "@/components/listings/ContactPanel";
import { EntryCosts } from "@/components/listings/EntryCosts";
import { ListingCard } from "@/components/listings/ListingCard";
import { PassportPanel } from "@/components/listings/PassportPanel";
import { formatPrice, TRANSACTION_LABELS } from "@/lib/format";
import { getListing, getSimilar, type ListingDetail } from "@/lib/listings";

type Props = PageProps<"/annonces/[reference]">;

export async function generateMetadata(props: Props): Promise<Metadata> {
  const { reference } = await props.params;
  const l = await getListing(reference);
  if (!l) notFound(); // 404 réel : les moteurs ne doivent pas indexer une annonce inexistante ou retirée

  const title = `${l.title ?? "Annonce"} — ${l.property.locality?.name ?? "Dakar"}`;
  const description = `${formatPrice(l.price_xof, l.transaction)}. ${(l.description ?? "").slice(0, 140)}`.trim();
  const image = l.property.images.find((i) => i.is_cover)?.urls.large;
  return {
    title,
    description,
    alternates: { canonical: `/annonces/${l.reference}` },
    openGraph: { title, description, url: `/annonces/${l.reference}`, type: "website", locale: "fr_SN", images: image ? [{ url: image }] : undefined },
  };
}

/** Données structurées (échappées contre l'injection de balises dans le JSON). */
function jsonLd(l: ListingDetail) {
  const data = {
    "@context": "https://schema.org",
    "@type": "RealEstateListing",
    name: l.title,
    url: `/annonces/${l.reference}`,
    datePosted: l.published_at,
    description: l.description,
    offers: l.price_xof ? { "@type": "Offer", price: l.price_xof, priceCurrency: "XOF" } : undefined,
    about: { "@type": "Place", address: { "@type": "PostalAddress", addressLocality: l.property.locality?.name, addressCountry: "SN" } },
  };
  return JSON.stringify(data).replace(/</g, "\\u003c");
}

async function Detail({ params }: { params: Props["params"] }) {
  const { reference } = await params;
  const l = await getListing(reference);
  if (!l) notFound();
  const similar = await getSimilar(reference);
  const cover = l.property.images[0];

  return (
    <article className="flex flex-col gap-4">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLd(l) }} />
      <p className="text-sm text-zinc-600">
        {TRANSACTION_LABELS[l.transaction]} · {l.property.type?.label} · Réf. {l.reference}
      </p>
      <h1 className="text-2xl font-semibold">{l.title ?? "Annonce"}</h1>
      <p className="text-xl">
        {formatPrice(l.price_xof, l.transaction)}
        {l.negotiable ? " (négociable)" : ""}
      </p>

      {l.property.images.length > 0 && (
        <ul className="grid gap-2 sm:grid-cols-2">
          {l.property.images.map((img, i) => (
            <li key={img.id}>
              <Image
                src={img.urls.large ?? img.urls.medium ?? ""}
                alt={`${l.title ?? "Photo du bien"}${i === 0 ? " (photo principale)" : ""}`}
                width={800}
                height={600}
                unoptimized
                loading={cover && i === 0 ? "eager" : "lazy"}
                className="w-full bg-zinc-100 object-cover"
              />
            </li>
          ))}
        </ul>
      )}

      <section aria-labelledby="caracteristiques" className="flex flex-col gap-2">
        <h2 id="caracteristiques" className="text-lg font-semibold">
          Caractéristiques
        </h2>
        <ul className="flex flex-wrap gap-x-6 gap-y-1">
          {l.property.surface_m2 && <li>{l.property.surface_m2} m²</li>}
          {l.property.rooms !== null && <li>{l.property.rooms} pièces</li>}
          {l.property.bedrooms !== null && <li>{l.property.bedrooms} chambres</li>}
          {l.property.bathrooms !== null && <li>{l.property.bathrooms} salles de bain</li>}
          {l.property.furnished && <li>Meublé</li>}
          {l.property.amenities.map((a) => (
            <li key={a.code}>{a.label}</li>
          ))}
        </ul>
        {l.property.location && (
          <a
            className="underline"
            href={`https://www.openstreetmap.org/?mlat=${l.property.location.lat}&mlon=${l.property.location.lon}#map=16/${l.property.location.lat}/${l.property.location.lon}`}
            rel="noopener noreferrer"
            target="_blank"
          >
            Voir la zone sur la carte{l.property.location.precision === "exact" ? "" : " (position approximative)"}
          </a>
        )}
      </section>

      {l.description && <p className="whitespace-pre-line">{l.description}</p>}
      {l.entry_costs && <EntryCosts costs={l.entry_costs} />}
      <PassportPanel passport={l.passport} />

      <section aria-labelledby="annonceur" className="rounded border border-zinc-300 p-4">
        <h2 id="annonceur" className="text-lg font-semibold">
          Annonceur
        </h2>
        {l.advertiser.agency ? (
          <p>
            <Link className="underline" href={`/agences/${l.advertiser.agency.slug}`}>
              {l.advertiser.name}
            </Link>{" "}
            · Agence{l.advertiser.agency.verified ? " vérifiée" : ""}
          </p>
        ) : (
          <p>{l.advertiser.name} · Particulier · téléphone vérifié</p>
        )}
      </section>
      <ContactPanel listingId={l.id} reference={l.reference} />

      {similar.length > 0 && (
        <section aria-labelledby="similaires" className="flex flex-col gap-2">
          <h2 id="similaires" className="text-lg font-semibold">
            Biens similaires
          </h2>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {similar.map((s) => (
              <li key={s.id}>
                <ListingCard listing={s} />
              </li>
            ))}
          </ul>
        </section>
      )}
      <Link href="/annonces" className="underline">
        ← Retour aux résultats
      </Link>
    </article>
  );
}

export default function Page(props: Props) {
  return (
    <main className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
      <Suspense fallback={<p>Chargement…</p>}>
        <Detail params={props.params} />
      </Suspense>
    </main>
  );
}
