import Link from "next/link";
import { formatPrice } from "@/lib/format";
import type { ListingSummary } from "@/lib/listings";
import { SafeImage } from "@/components/SafeImage";
import { AvailabilityBadge } from "./AvailabilityBadge";

/** Carte d'annonce du design. `sponsored` : annonce mise en avant, toujours étiquetée comme telle. */
export function ListingCard({ listing, sponsored = false, layout = "column", headingLevel = 3 }: { listing: ListingSummary; sponsored?: boolean; layout?: "column" | "row"; headingLevel?: 2 | 3 }) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const image = listing.cover?.medium ?? listing.cover?.thumb;
  const facts = [listing.property_type_label ?? listing.property_type, listing.surface_m2 ? `${listing.surface_m2} m²` : null, listing.bedrooms ? `${listing.bedrooms} ch.` : null].filter(Boolean).join(" · ");
  return (
    <article className="overflow-hidden rounded-xl border border-line bg-white shadow-card transition hover:-translate-y-0.5 hover:border-[#e5b999] hover:shadow-panel">
      <Link href={`/annonces/${listing.reference}`} className={layout === "row" ? "grid grid-cols-[8.5rem_1fr] sm:grid-cols-[16rem_1fr]" : "flex flex-col"}>
        <div className={`relative overflow-hidden bg-sand-deep ${layout === "row" ? "min-h-40 sm:min-h-44" : "aspect-[4/3]"}`}>
          <SafeImage src={image ?? ""} alt={listing.title ?? "Photo du bien"} className="size-full object-cover" fallbackLabel="Pas de photo" />
          {sponsored && (
            <span className="absolute bottom-2.5 left-2.5 rounded-md bg-ink/85 px-2.5 py-1.5 text-[0.65rem] font-bold text-white">Mise en avant par son annonceur</span>
          )}
        </div>
        <div className="flex flex-col gap-1 p-4">
          {listing.locality && <p className="text-xs font-semibold text-muted">{listing.locality.name}</p>}
          <Heading className="text-base leading-snug font-bold">{listing.title ?? "Annonce"}</Heading>
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
