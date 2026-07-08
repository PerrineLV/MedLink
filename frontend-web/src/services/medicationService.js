import httpClient from './httpClient'

export async function searchMedications(query) {
  const response = await httpClient.get('/medications/search', { params: { q: query } })

  return response.data.map((item) => ({ name: item.name, suggestedDosage: item.suggestedDosage ?? null }))
}

export async function fetchMedicationMetadata() {
  const response = await httpClient.get('/medications/metadata')

  return response.data
}
