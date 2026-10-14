import { describe, expect, it } from "vitest";
import { normalizeSenegalPhone } from "./phone";

describe("normalizeSenegalPhone", () => {
  it.each(["771234567", "77 123 45 67", "+221 77 123 45 67", "00221771234567", "221771234567"])(
    "normalise %s",
    (input) => expect(normalizeSenegalPhone(input)).toBe("+221771234567"),
  );

  it.each(["", "12345", "+33612345678", "871234567", "77123456"])("rejette %s", (input) =>
    expect(normalizeSenegalPhone(input)).toBeNull(),
  );
});
