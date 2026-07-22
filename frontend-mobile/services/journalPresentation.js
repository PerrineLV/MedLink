// Charte MedLink / dossier de certification — couleurs de badges (contraste
// RGAA vérifié pour la paire rouge ; vert et orange suivent le même écart de
// luminosité entre fond pastel et texte saturé pour rester au moins aussi
// contrastés).
export const COLORS = {
  primary: '#2e3862',
  onPrimary: '#ffffff',
  background: '#f4f6fb',
  surface: '#ffffff',
  border: '#e1e4ef',
  mutedText: '#666666',
  mutedBackground: '#f4f6fb',
  red: { bg: '#fde8ec', text: '#c0143c' },
  orange: { bg: '#fdf1dc', text: '#92400e' },
  green: { bg: '#e3f5ea', text: '#1e7e46' },
};

// React Native n'a pas d'unité "rem" (tout est en dp) ; cette échelle
// reprend la progression rem du dossier de certification sur une base de
// 16dp pour garder les rapports de taille identiques.
const REM = 16;
export const TYPE = {
  xs: REM * 0.75, // 12
  sm: REM * 0.875, // 14
  base: REM * 1, // 16
  md: REM * 1.125, // 18
  lg: REM * 1.25, // 20
  xl: REM * 1.5, // 24
};

// Mood is 1 (worst) to 5 (best); pain is 0 (none) to 10 (worst) — so the
// bands read in opposite directions on their respective scales.
export function moodBand(mood) {
  if (mood >= 4) return { label: 'Bonne', ...COLORS.green };
  if (mood === 3) return { label: 'Moyenne', ...COLORS.orange };
  return { label: 'Mauvaise', ...COLORS.red };
}

export function painBand(painLevel) {
  if (painLevel <= 3) return { label: 'Légère', ...COLORS.green };
  if (painLevel <= 6) return { label: 'Modérée', ...COLORS.orange };
  return { label: 'Élevée', ...COLORS.red };
}

export function bloodPressureBand(bloodPressure) {
  const [systolic, diastolic] = bloodPressure.split('/').map(Number);
  if (systolic >= 140 || diastolic >= 90) return { label: 'Élevée', ...COLORS.red };

  return { label: 'Normale', ...COLORS.green };
}
