import httpClient from './httpClient';

export async function fetchTreatments() {
  const response = await httpClient.get('/treatments');
  return response.data;
}

export async function toggleTreatmentIntake(intakeId) {
  const response = await httpClient.patch(`/treatment-intakes/${intakeId}/toggle`);
  return response.data;
}

export const MOMENT_LABELS = {
  morning: 'Matin',
  noon: 'Midi',
  evening: 'Soir',
};

export function scheduleLabel(schedule) {
  return schedule.moment === 'custom' ? schedule.customLabel : MOMENT_LABELS[schedule.moment];
}
