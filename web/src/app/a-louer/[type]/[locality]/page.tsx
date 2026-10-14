import type { Metadata } from "next";
import { Suspense } from "react";
import { SeoListingPage, seoMetadata, type SeoParams } from "@/components/listings/SeoListingPage";

async function parse(params: PageProps<"/a-louer/[type]/[locality]">["params"]): Promise<SeoParams> {
  const { type, locality } = await params;
  return { transaction: "rent_long", type, locality };
}

export async function generateMetadata(props: PageProps<"/a-louer/[type]/[locality]">): Promise<Metadata> {
  return seoMetadata(await parse(props.params));
}

async function Body({ params }: { params: PageProps<"/a-louer/[type]/[locality]">["params"] }) {
  return <SeoListingPage p={await parse(params)} />;
}

export default function Page(props: PageProps<"/a-louer/[type]/[locality]">) {
  return (
    <Suspense fallback={<p className="p-6">Chargement…</p>}>
      <Body params={props.params} />
    </Suspense>
  );
}
