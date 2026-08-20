/** Shared client-side input validation rules. */

export const LIMITS = {
  name: { min: 3, max: 60 },
  phone: { min: 9, max: 15 },
  address: { max: 120 },
  location: { max: 80 },
  subCategory: { max: 60 },
  description: { min: 20, max: 500 },
  comment: { max: 300 },
  username: { min: 3, max: 24 },
};

const NAME_RE = /^[A-Za-z\u1200-\u137F'’.\- ]+$/;
const PHONE_RE = /^(\+251|0)?9\d{8}$/;

/** Letters/spaces only — rejects digits and symbols. */
export function validateName(value: string, field = "Name"): string | null {
  const v = value.trim();
  if (!v) return `${field} is required`;
  if (/\d/.test(v)) return `${field} cannot contain numbers`;
  if (!NAME_RE.test(v)) return `${field} may only contain letters`;
  if (v.length < LIMITS.name.min) return `${field} must be at least ${LIMITS.name.min} characters`;
  if (v.length > LIMITS.name.max) return `${field} must be under ${LIMITS.name.max} characters`;
  return null;
}

/** Digits only, Ethiopian mobile format. */
export function validatePhone(value: string, required = true): string | null {
  const v = value.replace(/[\s-]/g, "").trim();
  if (!v) return required ? "Phone number is required" : null;
  if (/[A-Za-z]/.test(v)) return "Phone number cannot contain letters";
  if (/[^0-9+]/.test(v)) return "Phone number may only contain digits";
  if (!PHONE_RE.test(v)) return "Enter a valid phone number, e.g. 0912345678 or +251912345678";
  return null;
}

export function validateText(
  value: string,
  field: string,
  { min = 0, max = 200, required = true }: { min?: number; max?: number; required?: boolean } = {},
): string | null {
  const v = value.trim();
  if (!v) return required ? `${field} is required` : null;
  if (v.length < min) return `${field} must be at least ${min} characters`;
  if (v.length > max) return `${field} must be under ${max} characters`;
  return null;
}

/** Strips everything that is not a digit or a leading plus. */
export const sanitizePhoneInput = (value: string) =>
  value.replace(/[^\d+]/g, "").replace(/(?!^)\+/g, "").slice(0, LIMITS.phone.max + 4);

/** Strips digits from name-like inputs as the user types. */
export const sanitizeNameInput = (value: string) =>
  value.replace(/[^A-Za-z\u1200-\u137F'’.\- ]/g, "").slice(0, LIMITS.name.max);
