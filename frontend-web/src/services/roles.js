export const ROLE_PATIENT = 'ROLE_PATIENT'
export const ROLE_AIDANT = 'ROLE_AIDANT'
export const ROLE_SOIGNANT = 'ROLE_SOIGNANT'
export const ROLE_ADMIN = 'ROLE_ADMIN'

const ROLE_PRIORITY = [ROLE_ADMIN, ROLE_SOIGNANT, ROLE_AIDANT, ROLE_PATIENT]

export const ROLE_LABELS = {
  [ROLE_PATIENT]: 'Patient',
  [ROLE_AIDANT]: 'Aidant',
  [ROLE_SOIGNANT]: 'Soignant',
  [ROLE_ADMIN]: 'Administrateur',
}

/**
 * A user can technically hold several roles; this picks the one that
 * should drive which dashboard content is shown.
 */
export function getPrimaryRole(roles = []) {
  return ROLE_PRIORITY.find((role) => roles.includes(role)) ?? null
}

/**
 * Where to land right after login. A soignant goes straight to their
 * patient list (ML-24), a patient/aidant to their own journal (ML-41);
 * other roles (admin) keep the generic dashboard until their own dedicated
 * page exists.
 */
export function getHomeRoute(roles = []) {
  if (roles.includes(ROLE_SOIGNANT)) return '/patients'
  if (roles.includes(ROLE_PATIENT) || roles.includes(ROLE_AIDANT)) return '/journal'

  return '/dashboard'
}
