import httpClient from './httpClient';

export async function fetchLiaisons() {
  const response = await httpClient.get('/liaisons');
  return response.data;
}

export async function inviteLiaison(email) {
  const response = await httpClient.post('/liaisons/invitations', { email });
  return response.data;
}

export async function revokeLiaison(id) {
  const response = await httpClient.patch(`/liaisons/${id}/revoquer`);
  return response.data;
}

export async function fetchReceivedInvitations() {
  const response = await httpClient.get('/liaisons/invitations');
  return response.data;
}

export async function acceptInvitation(id) {
  const response = await httpClient.patch(`/liaisons/invitations/${id}/accepter`);
  return response.data;
}

export async function rejectInvitation(id) {
  const response = await httpClient.patch(`/liaisons/invitations/${id}/refuser`);
  return response.data;
}
