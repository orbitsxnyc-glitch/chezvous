import { describe, expect, it } from "vitest";
import { canActivate, SETTLEMENT_LABELS } from "./adminCommercial";

describe("règlements", () => {
  it("n'autorise l'activation qu'avec un encaissement confirmé (ou un crédit inclus)", () => {
    expect(canActivate("pending", "confirmed")).toBe(true);
    expect(canActivate("pending", "not_required")).toBe(true);
    expect(canActivate("pending", "declared")).toBe(false);
    expect(canActivate("pending", "unpaid")).toBe(false);
    expect(canActivate("active", "confirmed")).toBe(false);
  });

  it("distingue toujours le déclaré du confirmé", () => {
    expect(SETTLEMENT_LABELS.declared).not.toBe(SETTLEMENT_LABELS.confirmed);
    expect(SETTLEMENT_LABELS.declared).toContain("non confirmé");
  });
});
