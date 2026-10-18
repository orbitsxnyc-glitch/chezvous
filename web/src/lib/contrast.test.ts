import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";
import { contrast } from "./contrast";

/** Jetons lus dans globals.css : un changement de palette qui dégrade la lisibilité fait échouer ce test. */
const css = readFileSync(join(__dirname, "../app/globals.css"), "utf8");
const token = (name: string): string => {
  const m = css.match(new RegExp(`--${name}:\\s*(#[0-9a-fA-F]{6})`));
  if (!m) throw new Error(`jeton --${name} introuvable`);
  return m[1];
};
const WHITE = "#ffffff";

describe("contraste des couleurs (WCAG AA)", () => {
  const normalText: [string, string, string][] = [
    ["ink", "surface", "texte sur fond blanc"], ["ink", "sand", "texte sur sable"], ["ink", "surface-soft", "texte sur fond de page"],
    ["muted", "surface", "texte secondaire sur blanc"], ["muted", "sand", "texte secondaire sur sable"], ["muted", "surface-soft", "texte secondaire sur fond de page"], ["muted", "primary-soft", "texte secondaire sur fond doux"],
    ["primary", "surface", "lien ou texte principal sur blanc"], ["primary", "surface-soft", "lien sur fond de page"], ["primary", "sand", "lien sur sable"], ["primary", "primary-soft", "lien sur fond doux"],
    ["danger", "surface", "erreur sur blanc"], ["success", "surface", "succès sur blanc"],
  ];
  it.each(normalText)("%s sur %s ≥ 4,5 : %s", (fg, bg) => {
    expect(contrast(token(fg), token(bg))).toBeGreaterThanOrEqual(4.5);
  });

  it("le blanc reste lisible sur les boutons et sections de couleur", () => {
    expect(contrast(WHITE, token("primary"))).toBeGreaterThanOrEqual(4.5);
    expect(contrast(WHITE, token("primary-hover"))).toBeGreaterThanOrEqual(4.5);
    expect(contrast("#ffe9da", token("primary-hover"))).toBeGreaterThanOrEqual(4.5); // texte doux de la section « Pourquoi ChezVous »
  });

  it("l'orange vif de la marque ne porte que du texte sombre ; l'interface utilise l'orange profond", () => {
    // Blanc sur orange vif : 2,9 (sous 4,5) -> interdit pour du texte ou un bouton ; l'anthracite y est lisible.
    expect(contrast(WHITE, token("brand-orange"))).toBeLessThan(4.5);
    expect(contrast(token("ink"), token("brand-orange"))).toBeGreaterThanOrEqual(4.5);
    expect(contrast(token("ink"), token("brand-amber"))).toBeGreaterThanOrEqual(4.5);
  });
});
