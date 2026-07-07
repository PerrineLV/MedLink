import httpClient from './httpClient'

export async function fetchJournalEntries(patientId) {
  const response = await httpClient.get('/journal_entries', { params: { patient: patientId } })

  return response.data
}

export async function createComment(journalEntryId, text) {
  const response = await httpClient.post('/journal_entry_comments', { journalEntryId, text })

  return response.data
}

export async function createJournalEntry({ patientId, mood, painLevel, bloodPressure, note }) {
  const response = await httpClient.post('/journal_entries', {
    patientId,
    mood,
    painLevel,
    bloodPressure,
    note: note || null,
  })

  return response.data
}
