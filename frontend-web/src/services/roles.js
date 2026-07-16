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
 * Single point of truth for how a soignant's name is displayed to a patient
 * or aidant (ML-72) — prefixes the professional title (ex. "Dr") set at
 * signup, without ever dropping firstName/lastName.
 */
export function formatSoignantName(firstName, lastName, title) {
  return title ? `${title} ${firstName} ${lastName}` : `${firstName} ${lastName}`;
}

/**
 * A user can technically hold several roles; this picks the one that
 * should drive which dashboard content is shown.
 */
export function getPrimaryRole(roles = []) {
  return ROLE_PRIORITY.find((role) => roles.includes(role)) ?? null;
}

/**
 * Where to land right after login. A soignant goes straight to their
 * patient list (ML-24), a patient/aidant to their own journal (ML-41), an
 * admin to the user management screen (ML-54); anyone without one of these
 * roles (should not happen in practice) is sent back to login rather than a
 * dead-end page (ML-62 — there is no generic dashboard anymore).
 */
export function getHomeRoute(roles = []) {
  if (roles.includes(ROLE_SOIGNANT)) return '/patients';
  if (roles.includes(ROLE_PATIENT) || roles.includes(ROLE_AIDANT)) return '/journal';
  if (roles.includes(ROLE_ADMIN)) return '/admin/users';

  return '/login';
}

const SOIGNANT_SIDEBAR_ITEMS = [
  { key: 'patients', label: 'Patients', to: '/patients' },
  { key: 'invitations', label: 'Invitations', to: '/invitations' },
  { key: 'messages', label: 'Messages', to: '/messages' },
  { key: 'agenda', label: 'Agenda', to: '/agenda' },
  { key: 'export', label: 'Export', to: '/export' },
  { key: 'parametres', label: 'Paramètres', to: null },
  { key: 'compte', label: 'Mon compte', to: '/account' },
];

const PATIENT_SIDEBAR_ITEMS = [
  { key: 'journal', label: 'Journal', to: '/journal' },
  { key: 'traitements', label: 'Traitements', to: null },
  { key: 'liaisons', label: 'Mes liaisons', to: '/liaisons' },
  { key: 'messagerie', label: 'Messagerie', to: '/messages' },
  { key: 'rdv', label: 'Rendez-vous', to: '/agenda' },
  { key: 'export', label: 'Export PDF', to: '/export' },
  { key: 'compte', label: 'Mon compte', to: '/account' },
];

const AIDANT_SIDEBAR_ITEMS = [
  ...PATIENT_SIDEBAR_ITEMS.filter((item) => item.key !== 'liaisons'),
  { key: 'invitations', label: 'Invitations', to: '/invitations' },
];

const ADMIN_SIDEBAR_ITEMS = [
  { key: 'utilisateurs', label: 'Utilisateurs', to: '/admin/users' },
  { key: 'supervision', label: 'Supervision', to: '/admin/supervision' },
  { key: 'compte', label: 'Mon compte', to: '/account' },
];

/**
 * Sidebar menu for AppLayout: patient/aidant get their own shortcuts
 * (Journal/Traitements/Messagerie/Rendez-vous/Export PDF, ML-41), everyone
 * else keeps the soignant/admin menu. "Mes liaisons" (ML-47) is
 * patient-only — an aidant never manages the patient's own consent
 * relationships (cf. principe RGPD consent-first) — so it's dropped for a
 * session that only holds ROLE_AIDANT, replaced by "Invitations" (ML-48,
 * the invitations *received* by the aidant, not the patient's own links).
 */
export function getSidebarItems(roles = []) {
  if (roles.includes(ROLE_PATIENT)) return PATIENT_SIDEBAR_ITEMS;
  if (roles.includes(ROLE_AIDANT)) return AIDANT_SIDEBAR_ITEMS;
  if (roles.includes(ROLE_ADMIN)) return ADMIN_SIDEBAR_ITEMS;

  return SOIGNANT_SIDEBAR_ITEMS;
}
