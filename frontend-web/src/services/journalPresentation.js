// Charte MedLink / dossier de certification — mêmes bandes et couleurs de
// badges que le mobile (voir ML-23), exposées ici via les variables CSS
// --color-red-*/--color-orange-*/--color-green-* (index.css).

// Mood is 1 (worst) to 5 (best); pain is 0 (none) to 10 (worst) — so the
// bands read in opposite directions on their respective scales.
export function moodBand(mood) {
  if (mood >= 4) return { level: 'green', label: 'Bonne' };
  if (mood === 3) return { level: 'orange', label: 'Moyenne' };
  return { level: 'red', label: 'Mauvaise' };
}

export function painBand(painLevel) {
  if (painLevel <= 3) return { level: 'green', label: 'Légère' };
  if (painLevel <= 6) return { level: 'orange', label: 'Modérée' };
  return { level: 'red', label: 'Élevée' };
}

export function bloodPressureBand(bloodPressure) {
  const [systolic, diastolic] = bloodPressure.split('/').map(Number);
  if (systolic >= 140 || diastolic >= 90) return { level: 'red', label: 'Élevée' };

  return { level: 'green', label: 'Normale' };
}

const STALE_ENTRY_DAYS = 3;

/**
 * Status badge for a patient card (ML-24): computed from their most recent
 * entry only. A patient who hasn't logged anything in a while is flagged
 * regardless of how good their last reading was — that's a distinct,
 * explicit state, not folded into the color bands.
 */
export function patientStatusBand(entries) {
  if (entries.length === 0) {
    return { level: 'orange', label: 'Aucune entrée' };
  }

  const [lastEntry] = entries;
  const daysSinceLastEntry = Math.floor(
    (Date.now() - new Date(lastEntry.createdAt).getTime()) / 86_400_000,
  );

  if (daysSinceLastEntry > STALE_ENTRY_DAYS) {
    return { level: 'orange', label: `Aucune entrée depuis ${daysSinceLastEntry} jours` };
  }

  const pain = painBand(lastEntry.painLevel);
  const bloodPressure = bloodPressureBand(lastEntry.bloodPressure);
  const mood = moodBand(lastEntry.mood);

  const worst = [
    { ...pain, subject: 'Douleur' },
    { ...bloodPressure, subject: 'Tension' },
    { ...mood, subject: 'Humeur' },
  ];

  const red = worst.find((indicator) => indicator.level === 'red');
  if (red) return { level: 'red', label: `${red.subject} ${red.label.toLowerCase()}` };

  const orange = worst.find((indicator) => indicator.level === 'orange');
  if (orange) return { level: 'orange', label: `${orange.subject} ${orange.label.toLowerCase()}` };

  return { level: 'green', label: 'Stable' };
}
