import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { connection } from "next/server";
import { Suspense } from "react";
import { ContactPanel } from "@/components/listings/ContactPanel";
import { SafeImage } from "@/components/SafeImage";
import { AvailabilityBadge } from "@/components/listings/AvailabilityBadge";
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

const fmtM2 = (n: number) => `${n} m²`;

async function Detail({ params }: { params: Props["params"] }) {
  await connection(); // la fraîcheur affichée dépend de l'heure courante : rendu à la demande
  const { reference } = await params;
  const l = await getListing(reference);
  if (!l) notFound();
  const similar = await getSimilar(reference);
  const images = l.property.images;
  const p = l.property;
  // « Téléphone vérifié » ne s'affiche que si le contrôle figure au Passeport : un compte vérifié par e-mail n'a pas de numéro prouvé.
  const phoneProven = l.passport.checks.some((c) => c.type === "phone");
  const facts: [string, string][] = [
    p.type ? [p.type.label, "Type"] : null,
    p.surface_m2 ? [fmtM2(p.surface_m2), "Surface"] : null,
    p.bedrooms !== null ? [String(p.bedrooms), "Chambres"] : null,
    p.bathrooms !== null ? [String(p.bathrooms), "Salles de bain"] : null,
    p.rooms !== null ? [String(p.rooms), "Pièces"] : null,
  ].filter((x): x is [string, string] => x !== null);

  return (
    <article className="flex flex-col gap-6">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLd(l) }} />
      <nav aria-label="Fil d'Ariane" className="text-xs text-muted">
        <Link href="/" className="underline">Accueil</Link> <span aria-hidden>/</span>{" "}
        <Link href="/annonces" className="underline">Rechercher</Link> <span aria-hidden>/</span> {l.reference}
      </nav>

      {images.length > 0 ? (
        <ul className={`grid gap-2 overflow-hidden rounded-xl md:h-[28rem] ${images.length > 1 ? "md:grid-cols-[2fr_1fr] md:grid-rows-2" : ""}`}>
          {images.slice(0, 3).map((img, i) => (
            <li key={img.id} className={`min-h-0 ${i === 0 && images.length > 1 ? "md:row-span-2" : ""}`}>
              <SafeImage
                src={img.urls.large ?? img.urls.medium ?? ""}
                alt={`${l.title ?? "Photo du bien"}${i === 0 ? " (photo principale)" : ""}`}
                eager={i === 0}
                className={`w-full bg-sand-deep object-cover ${i === 0 ? "aspect-[4/3] md:aspect-auto md:h-full" : "hidden md:block md:h-full"}`}
              />
            </li>
          ))}
        </ul>
      ) : (
        <div className="flex h-56 items-center justify-center rounded-xl bg-sand-deep text-muted">Aucune photo</div>
      )}
      {images.length > 3 && <p className="-mt-3 text-xs text-muted">{images.length} photos au total.</p>}

      <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_21rem]">
        <div className="flex min-w-0 flex-col gap-8">
          <header className="flex flex-col gap-2">
            <p className="text-xs font-semibold text-muted">{TRANSACTION_LABELS[l.transaction]}{p.locality ? ` · ${p.locality.name}` : ""} · Réf. {l.reference}</p>
            <h1 className="text-3xl leading-tight font-extrabold">{l.title ?? "Annonce"}</h1>
            <p className="flex flex-wrap items-baseline gap-3">
              <strong className="text-2xl font-extrabold text-primary">{formatPrice(l.price_xof, l.transaction)}</strong>
              {l.negotiable && <span className="rounded-md bg-sand px-2 py-1 text-xs font-bold">Prix négociable</span>}
            </p>
            <AvailabilityBadge state={l.passport.availability.state} confirmedAt={l.passport.availability.confirmed_at} />
          </header>

          {facts.length > 0 && (
            <dl className="grid grid-cols-2 gap-y-4 border-y border-line py-5 sm:grid-cols-4 lg:grid-cols-5">
              {facts.map(([value, name]) => (
                <div key={name} className="flex flex-col border-line px-4 first:pl-0 sm:border-r sm:last:border-0">
                  <dd className="order-first text-base font-extrabold">{value}</dd>
                  <dt className="mt-0.5 text-xs text-muted">{name}</dt>
                </div>
              ))}
            </dl>
          )}

          {l.description && (
            <section aria-labelledby="description" className="flex flex-col gap-2">
              <h2 id="description" className="text-xl font-extrabold">Description</h2>
              <p className="leading-7 whitespace-pre-line text-muted">{l.description}</p>
            </section>
          )}

          {(p.amenities.length > 0 || p.furnished) && (
            <section aria-labelledby="equipements" className="flex flex-col gap-3">
              <h2 id="equipements" className="text-xl font-extrabold">Équipements</h2>
              <ul className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                {p.furnished && <li className="flex items-center gap-2 text-sm"><span aria-hidden className="text-success">✓</span> Meublé</li>}
                {p.amenities.map((a) => <li key={a.code} className="flex items-center gap-2 text-sm"><span aria-hidden className="text-success">✓</span> {a.label}</li>)}
              </ul>
            </section>
          )}

          {l.entry_costs && <EntryCosts costs={l.entry_costs} />}
          <PassportPanel passport={l.passport} />

          {p.location && (
            <p className="text-sm">
              <a
                className="font-bold text-primary underline"
                href={`https://www.openstreetmap.org/?mlat=${p.location.lat}&mlon=${p.location.lon}#map=16/${p.location.lat}/${p.location.lon}`}
                rel="noopener noreferrer"
                target="_blank"
              >
                Voir la zone sur la carte{p.location.precision === "exact" ? "" : " (position approximative)"}
              </a>
            </p>
          )}
        </div>

        <div className="flex flex-col gap-4 lg:sticky lg:top-24">
          <section aria-labelledby="annonceur" className="rounded-xl border border-line bg-white p-5 shadow-card">
            <h2 id="annonceur" className="text-sm font-extrabold text-muted uppercase">Annonceur</h2>
            <div className="mt-3 flex items-center gap-3">
              <span aria-hidden className="flex size-11 items-center justify-center rounded-full bg-primary-soft font-extrabold text-primary">{l.advertiser.name.trim().charAt(0).toUpperCase()}</span>
              <div>
                {l.advertiser.agency ? (
                  <>
                    <Link className="font-bold underline" href={`/agences/${l.advertiser.agency.slug}`}>{l.advertiser.name}</Link>
                    <p className="text-xs text-muted">Agence{l.advertiser.agency.verified ? " vérifiée" : ""}</p>
                  </>
                ) : (
                  <>
                    <p className="font-bold">{l.advertiser.name}</p>
                    <p className="text-xs text-muted">Particulier{phoneProven ? " · téléphone vérifié" : ""}</p>
                  </>
                )}
              </div>
            </div>
          </section>
          <ContactPanel listingId={l.id} reference={l.reference} />
        </div>
      </div>

      {similar.length > 0 && (
        <section aria-labelledby="similaires" className="flex flex-col gap-4">
          <h2 id="similaires" className="text-xl font-extrabold">Biens similaires</h2>
          <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {similar.map((s) => (
              <li key={s.id}><ListingCard listing={s} /></li>
            ))}
          </ul>
        </section>
      )}
    </article>
  );
}

export default function Page(props: Props) {
  return (
    <main className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-5 py-8">
      <Suspense fallback={<p>Chargement…</p>}>
        <Detail params={props.params} />
      </Suspense>
    </main>
  );
}
