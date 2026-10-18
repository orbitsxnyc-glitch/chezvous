import { describe, expect, it } from "vitest";
import { BOOKING_LABELS, needsPayment, nightsBetween, stayQuery } from "./stays";

describe("stayQuery", () => {
  it("ne garde que les critères renseignés", () => {
    expect(stayQuery({ check_in: "2027-03-10", check_out: "", guests: "2" })).toBe("?check_in=2027-03-10&guests=2");
    expect(stayQuery({})).toBe("");
  });
});

describe("nightsBetween", () => {
  it("compte les nuits sur [arrivée, départ)", () => {
    expect(nightsBetween("2027-03-10", "2027-03-13")).toBe(3);
  });
  it("renvoie 0 si les dates sont inversées, égales ou invalides", () => {
    expect(nightsBetween("2027-03-13", "2027-03-10")).toBe(0);
    expect(nightsBetween("2027-03-10", "2027-03-10")).toBe(0);
    expect(nightsBetween("x", "2027-03-10")).toBe(0);
  });
});

describe("états de séjour", () => {
  it("chaque état a un libellé en français", () => {
    for (const label of Object.values(BOOKING_LABELS)) expect(label.length).toBeGreaterThan(3);
  });
  it("seuls les séjours acceptés attendent un règlement", () => {
    expect(needsPayment({ state: "accepted" })).toBe(true);
    expect(needsPayment({ state: "awaiting_payment" })).toBe(true);
    expect(needsPayment({ state: "requested" })).toBe(false);
    expect(needsPayment({ state: "confirmed" })).toBe(false);
  });
});
