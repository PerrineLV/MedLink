import httpClient from './httpClient';

export async function fetchTreatments() {
  const response = await httpClient.get('/treatments');
  return response.data;
}

export async function toggleTreatmentIntake(intakeId) {
  const response = await httpClient.patch(`/treatment-intakes/${intakeId}/toggle`);
  return response.data;
}
