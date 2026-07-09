import httpClient from './httpClient';

export async function fetchPatients() {
  const response = await httpClient.get('/patients');

  return response.data;
}
