import httpClient from './httpClient';

export async function fetchJournalEntries() {
  const response = await httpClient.get('/journal_entries');
  return response.data;
}

export async function createJournalEntry({ patientId, mood, painLevel, bloodPressure, note }) {
  const response = await httpClient.post('/journal_entries', {
    patientId,
    mood,
    painLevel,
    bloodPressure,
    note: note || null,
  });
  return response.data;
}
