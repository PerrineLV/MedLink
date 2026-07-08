import httpClient from './httpClient'

export async function fetchTreatments(patientId) {
  const response = await httpClient.get('/treatments', { params: { patient: patientId } })

  return response.data
}

export async function createTreatment({ patientId, name, dosage, schedules }) {
  const response = await httpClient.post('/treatments', { patientId, name, dosage, schedules })

  return response.data
}

export async function toggleTreatmentIntake(intakeId) {
  const response = await httpClient.patch(`/treatment-intakes/${intakeId}/toggle`)

  return response.data
}

export const MOMENT_LABELS = {
  morning: 'Matin',
  noon: 'Midi',
  evening: 'Soir',
}

export function scheduleLabel(schedule) {
  return schedule.moment === 'custom' ? schedule.customLabel : MOMENT_LABELS[schedule.moment]
}
