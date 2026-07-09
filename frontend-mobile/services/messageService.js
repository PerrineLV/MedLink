import httpClient from './httpClient';
import { fetchLiaisons } from './liaisonService';
import { fetchPatients } from './patientService';
import { ROLE_PATIENT } from './roles';

export async function fetchMessages(conversationUserId) {
  const response = await httpClient.get('/messages', { params: { conversation: conversationUserId } });
  return response.data;
}

export async function sendMessage(recipientId, content) {
  const response = await httpClient.post('/messages', { recipientId, content });
  return response.data;
}

export async function markMessageRead(messageId) {
  const response = await httpClient.patch(`/messages/${messageId}/read`);
  return response.data;
}

/**
 * Who can be messaged depends on the role: a patient messages their
 * attached aidants/soignants (GET /api/liaisons — patient-only), while an
 * aidant/soignant messages their attached patients (GET /api/patients, same
 * endpoint ML-23 already uses to pick a patient for a journal entry).
 */
export async function fetchContacts(roles) {
  if (roles.includes(ROLE_PATIENT)) {
    const liaisons = await fetchLiaisons();

    return liaisons
      .filter((liaison) => liaison.active)
      .map((liaison) => ({
        id: liaison.inviteeId,
        firstName: liaison.inviteeFirstName,
        lastName: liaison.inviteeLastName,
        role: liaison.inviteeRole,
      }));
  }

  const patients = await fetchPatients();
  return patients.map((patient) => ({ ...patient, role: ROLE_PATIENT }));
}
