import httpClient from './httpClient';

export async function fetchMessages(conversationUserId) {
  const response = await httpClient.get('/messages', {
    params: { conversation: conversationUserId },
  });
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
 * Qui peut être contacté dépend de la matrice ML-70 (patient <-> soignant,
 * aidant <-> soignant via un patient commun, jamais patient <-> aidant) :
 * calculé côté backend (MessageableContacts, seule source de vérité,
 * partagée avec l'autorisation d'envoi) pour ne jamais diverger de ce que
 * l'API accepterait réellement. Pour une paire aidant/soignant, chaque
 * contact porte aussi viaPatients : le ou les patients communs qui
 * justifient l'autorisation, à afficher quand l'un des deux a plusieurs
 * patients (donc plusieurs contacts de ce type) sans savoir lequel
 * correspond à quel patient.
 */
export async function fetchContacts() {
  const response = await httpClient.get('/message-contacts');

  return response.data;
}
