import { describe, expect, it } from "vitest";
import { describeMissing, hasProcessingImages, toInt, type MyListing } from "./myListings";

describe("myListings", () => {
  it("convertit les entiers saisis", () => {
    expect(toInt("450000")).toBe(450000);
    expect(toInt("450 000")).toBe(450000);
    expect(toInt("")).toBeNull();
    expect(toInt("12,5")).toBeUndefined();
    expect(toInt("-3")).toBeUndefined();
    expect(toInt("abc")).toBeUndefined();
  });

  it("nomme les champs manquants en français", () => {
    expect(describeMissing(["title", "cover_image", "inconnu"])).toEqual(["le titre", "une photo de couverture", "inconnu"]);
  });

  it("détecte les photos en cours de traitement", () => {
    const base = { property: { images: [{ status: "ready" }] } } as unknown as MyListing;
    const busy = { property: { images: [{ status: "ready" }, { status: "processing" }] } } as unknown as MyListing;
    expect(hasProcessingImages(base)).toBe(false);
    expect(hasProcessingImages(busy)).toBe(true);
  });
});
