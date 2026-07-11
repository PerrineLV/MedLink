export const ROLE_PATIENT = 'ROLE_PATIENT';
export const ROLE_AIDANT = 'ROLE_AIDANT';
export const ROLE_SOIGNANT = 'ROLE_SOIGNANT';
export const ROLE_ADMIN = 'ROLE_ADMIN';

const ROLE_PRIORITY = [ROLE_ADMIN, ROLE_SOIGNANT, ROLE_AIDANT, ROLE_PATIENT];

export const ROLE_LABELS = {
  [ROLE_PATIENT]: 'Patient',
  [ROLE_AIDANT]: 'Aidant',
  [ROLE_SOIGNANT]: 'Soignant',
  [ROLE_ADMIN]: 'Administrateur',
};

/**
 * A user can technically hold several roles; this picks the one that
 * should drive which dashboard content is shown.
 */
export function getPrimaryRole(roles = []) {
  return ROLE_PRIORITY.find((role) => roles.includes(role)) ?? null;
}

/**
 * There is no admin interface on mobile (ML-73): admin is web-only. A
 * session is admin-only when it holds ROLE_ADMIN and none of the roles the
 * standard screens (Journal/Messages/RDV/Export) are built for — an admin
 * who is *also* e.g. a soignant still gets the normal app.
 */
export function isAdminOnlySession(roles = []) {
  const standardRoles = [ROLE_PATIENT, ROLE_AIDANT, ROLE_SOIGNANT];

  return roles.includes(ROLE_ADMIN) && !standardRoles.some((role) => roles.includes(role));
}
