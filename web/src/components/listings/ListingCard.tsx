import Image from "next/image";
import Link from "next/link";
import { formatPrice } from "@/lib/format";
import type { ListingSummary } from "@/lib/listings";
import { AvailabilityBadge } from "./AvailabilityBadge";

export function ListingCard({ listing }: { listing: ListingSummary }) {
  const image = listing.cover?.medium ?? listing.cover?.thumb;
  return (
    <article className="flex flex-col overflow-hidden rounded border border-zinc-300">
      <Link href={`/annonces/${listing.reference}`} className="flex flex-col gap-2">
        {image ? (
          <Image src={image} alt={listing.title ?? "Photo du bien"} width={400} height={300} unoptimized className="h-44 w-full bg-zinc-100 object-cover" />
        ) : (
          <div className="h-44 w-full bg-zinc-100" aria-hidden />
        )}
        <div className="flex flex-col gap-1 p-3">
          <h3 className="font-semibold">{listing.title ?? "Annonce"}</h3>
          <p className="text-lg">{formatPrice(listing.price_xof, listing.transaction)}</p>
          <p className="text-sm text-zinc-700">
            {[listing.locality?.name, listing.surface_m2 ? `${listing.surface_m2} m²` : null, listing.bedrooms ? `${listing.bedrooms} ch.` : null]
              .filter(Boolean)
              .join(" · ")}
          </p>
          <AvailabilityBadge state={listing.availability.state} confirmedAt={listing.availability.confirmed_at} />
          <p className="text-xs text-zinc-500">Réf. {listing.reference}</p>
        </div>
      </Link>
    </article>
  );
}
