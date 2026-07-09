import httpClient from './httpClient';

export const APPOINTMENT_STATUS = {
  PLANNED: 'planned',
  CANCELLED: 'cancelled',
  COMPLETED: 'completed',
};

const REMINDER_WINDOW_MS = 24 * 60 * 60 * 1000;

export async function fetchAppointments(patientId) {
  const response = await httpClient.get('/appointments', { params: { patient: patientId } });
  return response.data;
}

export async function createAppointment({ patientId, scheduledAt, notes }) {
  const response = await httpClient.post('/appointments', {
    patientId,
    scheduledAt,
    notes: notes || null,
  });
  return response.data;
}

// PATCH sur une ressource API Platform attend le format "merge-patch+json"
// (RFC 7396), pas le JSON standard des POST : sans ce Content-Type explicite
// l'API refuse la requête.
export async function cancelAppointment(appointmentId) {
  const response = await httpClient.patch(
    `/appointments/${appointmentId}`,
    { status: APPOINTMENT_STATUS.CANCELLED },
    { headers: { 'Content-Type': 'application/merge-patch+json' } },
  );
  return response.data;
}

export function isUpcoming(appointment) {
  return new Date(appointment.scheduledAt).getTime() >= Date.now();
}

/**
 * Rappel visuel ML-28 : un RDV planifié dans les 24h qui viennent. Purement
 * côté client (pas de notification push, cf. décision ML-28) — recalculé à
 * chaque affichage plutôt que stocké, pour ne jamais désynchroniser de
 * l'heure réelle.
 */
export function isWithin24Hours(appointment) {
  const diffMs = new Date(appointment.scheduledAt).getTime() - Date.now();
  return diffMs >= 0 && diffMs <= REMINDER_WINDOW_MS;
}
