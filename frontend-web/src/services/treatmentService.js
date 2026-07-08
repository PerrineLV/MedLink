import httpClient from './httpClient'

export async function fetchTreatments(patientId) {
  const response = await httpClient.get('/treatments', { params: { patient: patientId } })

  return response.data
}

export async function createTreatment({ patientId, name, dosage, scheduledTime }) {
  const response = await httpClient.post('/treatments', { patientId, name, dosage, scheduledTime })

  return response.data
}

export async function toggleTreatmentIntake(intakeId) {
  const response = await httpClient.patch(`/treatment-intakes/${intakeId}/toggle`)

  return response.data
}
