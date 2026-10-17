import Image from "next/image";
import Link from "next/link";
import { formatPrice } from "@/lib/format";
import type { ListingSummary } from "@/lib/listings";
import { AvailabilityBadge } from "./AvailabilityBadge";

/** Carte d'annonce du design. `sponsored` : annonce mise en avant, toujours étiquetée comme telle. */
export function ListingCard({ listing, sponsored = false }: { listing: ListingSummary; sponsored?: boolean }) {
  const image = listing.cover?.medium ?? listing.cover?.thumb;
  const facts = [listing.property_type, listing.surface_m2 ? `${listing.surface_m2} m²` : null, listing.bedrooms ? `${listing.bedrooms} ch.` : null].filter(Boolean).join(" · ");
  return (
    <article className="overflow-hidden rounded-xl border border-line bg-white shadow-card transition hover:-translate-y-0.5 hover:border-[#b8c8c1] hover:shadow-panel">
      <Link href={`/annonces/${listing.reference}`} className="flex flex-col">
        <div className="relative aspect-[4/3] overflow-hidden bg-sand-deep">
          {image ? (
            <Image src={image} alt={listing.title ?? "Photo du bien"} width={400} height={300} unoptimized className="size-full object-cover" />
          ) : (
            <div className="flex size-full items-center justify-center text-sm text-muted" aria-hidden>Pas de photo</div>
          )}
          {sponsored && (
            <span className="absolute bottom-2.5 left-2.5 rounded-md bg-ink/85 px-2.5 py-1.5 text-[0.65rem] font-bold text-white">Mise en avant par son annonceur</span>
          )}
        </div>
        <div className="flex flex-col gap-1 p-4">
          {listing.locality && <p className="text-xs font-semibold text-muted">{listing.locality.name}</p>}
          <h3 className="text-base leading-snug font-bold">{listing.title ?? "Annonce"}</h3>
          {facts && <p className="text-xs text-muted">{facts}</p>}
          <p className="mt-2 text-base font-extrabold text-primary">{formatPrice(listing.price_xof, listing.transaction)}</p>
          {listing.agency && (
            <p className="text-xs text-muted">
              {listing.agency.name}
              {listing.agency.verified ? " · Agence vérifiée" : ""}
            </p>
          )}
          <div className="mt-2">
            <AvailabilityBadge state={listing.availability.state} confirmedAt={listing.availability.confirmed_at} />
          </div>
          <p className="mt-1 text-[0.65rem] text-muted">Réf. {listing.reference}</p>
        </div>
      </Link>
    </article>
  );
}
