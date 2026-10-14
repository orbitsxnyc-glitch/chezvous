/**
 * Aide à la saisie uniquement : le serveur reste l'autorité de validation.
 * Reproduit la normalisation sénégalaise du backend (+221, 9 chiffres commençant par 3 ou 7).
 */
export function normalizeSenegalPhone(input: string): string | null {
  let digits = input.replace(/\D+/g, "");
  if (digits.startsWith("00221")) digits = digits.slice(5);
  else if (digits.startsWith("221") && digits.length > 9) digits = digits.slice(3);
  return /^[37]\d{8}$/.test(digits) ? `+221${digits}` : null;
}
