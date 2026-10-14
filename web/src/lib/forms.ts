import { ApiError } from "./api";

/** Premier message d'erreur par champ, prêt à afficher. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError)) return {};
  return Object.fromEntries(Object.entries(error.errors).map(([k, v]) => [k, v[0]]));
}

/** Message général (hors champs) à afficher pour une erreur de l'API. */
export function generalError(error: unknown): string {
  if (error instanceof ApiError) {
    return error.code === "validation_error" ? "" : error.message;
  }
  return "Une erreur est survenue.";
}
