import { describe, expect, it } from "vitest";
import { limitLabel, settlementLabel } from "./commercial";

describe("settlementLabel", () => {
  it("n'affiche « réglé » que pour un encaissement confirmé", () => {
    expect(settlementLabel("confirmed")).toBe("Règlement confirmé");
    for (const s of ["unpaid", "declared"] as const) expect(settlementLabel(s)).not.toMatch(/confirm[ée]$|pay[ée]/i);
  });
  it("présente une déclaration comme en attente", () => expect(settlementLabel("declared")).toContain("en attente"));
  it("signale un droit inclus", () => expect(settlementLabel("not_required")).toBe("Inclus dans votre formule"));
});

describe("limitLabel", () => {
  it("signale un quota à fixer", () => expect(limitLabel(null)).toBe("à fixer"));
  it("affiche un quota fixé", () => expect(limitLabel(10)).toBe("10"));
});
