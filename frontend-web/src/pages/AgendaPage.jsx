import { useCallback, useEffect, useMemo, useState } from 'react';
import { Bell } from 'lucide-react';
import AppLayout from '../components/AppLayout';
import PatientAutocomplete from '../components/PatientAutocomplete';
import { useAuth } from '../contexts/AuthContext';
import {
  APPOINTMENT_STATUS,
  cancelAppointment,
  createAppointment,
  fetchAppointments,
  isUpcoming,
  isWithin24Hours,
} from '../services/appointmentService';
import { fetchContacts } from '../services/messageService';
import { fetchPatients } from '../services/patientService';
import { ROLE_AIDANT, ROLE_SOIGNANT } from '../services/roles';
import './AgendaPage.css';

const GENERIC_LOAD_ERROR = 'Impossible de charger vos rendez-vous. Vérifiez votre connexion.';
const GENERIC_CANCEL_ERROR = "Impossible d'annuler ce rendez-vous. Réessayez.";
const GENERIC_CREATE_ERROR = "Impossible d'enregistrer ce rendez-vous, réessayez.";

function contactDisplayName(contact) {
  return contact ? `${contact.firstName} ${contact.lastName}` : 'Soignant';
}

function formatDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
}

function formatTime(isoDate) {
  return new Date(isoDate).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

export default function AgendaPage() {
  const { roles } = useAuth();
  const isSoignant = roles.includes(ROLE_SOIGNANT);
  const isAidant = roles.includes(ROLE_AIDANT);

  const [appointments, setAppointments] = useState(null);
  const [patients, setPatients] = useState([]);
  const [patientNamesById, setPatientNamesById] = useState({});
  const [soignantNamesById, setSoignantNamesById] = useState({});
  const [error, setError] = useState(null);

  // Un soignant a besoin du nom du patient (il en a plusieurs) ; un
  // patient/aidant a besoin du nom du soignant, calculé via les contacts
  // ML-70 (seule source déjà disponible pour ce lien patient <-> soignant).
  // Le nom du patient est aussi chargé dans ce second cas : un aidant suivant
  // plusieurs patients a besoin de savoir chez quel soignant *lequel* de ses
  // patients a rendez-vous (cf. showPatientName plus bas), pas seulement le
  // nom du soignant.
  const load = useCallback(async () => {
    setError(null);

    try {
      const [fetchedAppointments, fetchedPatients, contacts] = await Promise.all([
        fetchAppointments(),
        fetchPatients(),
        isSoignant ? Promise.resolve([]) : fetchContacts(),
      ]);
      setAppointments(fetchedAppointments);
      setPatients(fetchedPatients);
      setPatientNamesById(
        Object.fromEntries(
          fetchedPatients.map((patient) => [patient.id, `${patient.firstName} ${patient.lastName}`]),
        ),
      );
      setSoignantNamesById(
        Object.fromEntries(
          contacts
            .filter((contact) => contact.role === ROLE_SOIGNANT)
            .map((contact) => [contact.id, contactDisplayName(contact)]),
        ),
      );
    } catch {
      setError(GENERIC_LOAD_ERROR);
    }
  }, [isSoignant]);

  useEffect(() => {
    load();
  }, [load]);

  const handleCreated = useCallback((appointment) => {
    setAppointments((current) => [...(current ?? []), appointment]);
  }, []);

  const handleCancel = useCallback(async (appointment) => {
    try {
      const updated = await cancelAppointment(appointment.id);
      setAppointments((current) =>
        (current ?? []).map((item) => (item.id === updated.id ? updated : item)),
      );
    } catch {
      window.alert(GENERIC_CANCEL_ERROR);
    }
  }, []);

  const { upcoming, past } = useMemo(() => {
    const sorted = [...(appointments ?? [])].sort(
      (a, b) => new Date(a.scheduledAt) - new Date(b.scheduledAt),
    );
    return {
      upcoming: sorted.filter(isUpcoming),
      past: sorted.filter((appointment) => !isUpcoming(appointment)).reverse(),
    };
  }, [appointments]);

  // Contrairement au journal/aux traitements (affiché seulement si ≥ 2
  // patients parmi les entrées visibles), on l'affiche systématiquement pour
  // un aidant : le nom ne doit pas apparaître/disparaître selon que l'un de
  // ses patients a ou non un RDV en ce moment.
  const showPatientName = isAidant;

  const nameFor = useCallback(
    (appointment) =>
      isSoignant
        ? (patientNamesById[appointment.patientId] ?? 'Patient')
        : (soignantNamesById[appointment.soignantId] ?? 'Soignant'),
    [patientNamesById, soignantNamesById, isSoignant],
  );

  const patientNameFor = useCallback(
    (appointment) => (showPatientName ? patientNamesById[appointment.patientId] : null),
    [patientNamesById, showPatientName],
  );

  return (
    <AppLayout>
      <h1 className="agenda-title">Agenda</h1>

      {error && (
        <p className="agenda-error" role="alert">
          {error}
        </p>
      )}

      {!error && (
        <>
          {isSoignant && <NewAppointmentPanel patients={patients} onCreated={handleCreated} />}

          {appointments === null ? (
            <p className="agenda-loading">Chargement…</p>
          ) : (
            <div className="agenda-layout">
              <section className="agenda-section">
                <h2 className="agenda-section-heading">À venir</h2>
                {upcoming.length === 0 ? (
                  <p className="agenda-empty">Aucun rendez-vous à venir.</p>
                ) : (
                  <ul className="agenda-list">
                    {upcoming.map((appointment) => (
                      <AppointmentCard
                        key={appointment.id}
                        appointment={appointment}
                        name={nameFor(appointment)}
                        patientName={patientNameFor(appointment)}
                        isSoignant={isSoignant}
                        onCancel={handleCancel}
                      />
                    ))}
                  </ul>
                )}
              </section>

              <section className="agenda-section">
                <h2 className="agenda-section-heading">Passés</h2>
                {past.length === 0 ? (
                  <p className="agenda-empty">Aucun rendez-vous passé.</p>
                ) : (
                  <ul className="agenda-list">
                    {past.map((appointment) => (
                      <AppointmentCard
                        key={appointment.id}
                        appointment={appointment}
                        name={nameFor(appointment)}
                        patientName={patientNameFor(appointment)}
                        isSoignant={isSoignant}
                        isPast
                      />
                    ))}
                  </ul>
                )}
              </section>
            </div>
          )}
        </>
      )}
    </AppLayout>
  );
}

function AppointmentCard({
  appointment,
  name,
  patientName = null,
  isSoignant,
  isPast = false,
  onCancel,
}) {
  const showReminder =
    !isPast &&
    appointment.status === APPOINTMENT_STATUS.PLANNED &&
    isWithin24Hours(appointment);
  const isCancelled = appointment.status === APPOINTMENT_STATUS.CANCELLED;
  const canCancel = isSoignant && !isPast && appointment.status === APPOINTMENT_STATUS.PLANNED;
  const date = formatDate(appointment.scheduledAt);
  const time = formatTime(appointment.scheduledAt);

  const label = [
    patientName ? `Patient ${patientName}` : null,
    `${date}, ${name}, ${time}`,
    appointment.notes,
    isCancelled ? 'Rendez-vous annulé' : null,
    showReminder ? 'Rendez-vous dans moins de 24 heures' : null,
  ]
    .filter(Boolean)
    .join(', ');

  return (
    <li className={isPast ? 'agenda-card agenda-card-past' : 'agenda-card'} aria-label={label}>
      <div className="agenda-card-date" aria-hidden="true">
        <span className="agenda-card-day">{new Date(appointment.scheduledAt).getDate()}</span>
        <span className="agenda-card-month">
          {new Date(appointment.scheduledAt).toLocaleDateString('fr-FR', { month: 'short' }).replace('.', '')}
        </span>
      </div>

      <div className="agenda-card-info" aria-hidden="true">
        {patientName && <span className="agenda-card-patient-name">{patientName}</span>}
        <div className="agenda-card-header">
          <span className="agenda-card-name">{name}</span>
          {showReminder && <Bell className="agenda-reminder-icon" size={16} />}
        </div>
        <span className="agenda-card-meta">
          {time}
          {appointment.notes ? ` · ${appointment.notes}` : ''}
        </span>
        {isCancelled && <span className="agenda-cancelled-tag">Annulé</span>}
      </div>

      {canCancel && (
        <button
          type="button"
          className="agenda-cancel-button"
          onClick={() => onCancel(appointment)}
          aria-label={`Annuler le rendez-vous du ${date} avec ${name}`}
        >
          Annuler
        </button>
      )}
    </li>
  );
}

function NewAppointmentPanel({ patients, onCreated }) {
  const [isCreating, setIsCreating] = useState(false);
  const [selectedPatientId, setSelectedPatientId] = useState(null);
  const [patientQuery, setPatientQuery] = useState('');
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [consultationType, setConsultationType] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const resolvedPatientId = selectedPatientId ?? patients[0]?.id ?? null;

  const resetForm = () => {
    setSelectedPatientId(null);
    setPatientQuery('');
    setDate('');
    setTime('');
    setConsultationType('');
    setError(null);
  };

  const cancelPanel = () => {
    setIsCreating(false);
    resetForm();
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);

    // Le champ patient est une autosuggestion, pas un select : le texte
    // affiché doit correspondre exactement au patient sélectionné dans la
    // liste, sinon une frappe sans sélection créerait un RDV pour le dernier
    // patient valide au lieu de celui écrit à l'écran.
    if (patients.length > 1) {
      const selectedPatient = patients.find((patient) => patient.id === resolvedPatientId);
      const expectedLabel = selectedPatient
        ? `${selectedPatient.firstName} ${selectedPatient.lastName}`
        : null;
      if (!selectedPatient || patientQuery.trim() !== expectedLabel) {
        setError('Sélectionnez un patient dans la liste.');
        return;
      }
    }

    const scheduledAt = new Date(`${date}T${time}`);
    if (Number.isNaN(scheduledAt.getTime()) || scheduledAt.getTime() < Date.now()) {
      setError('La date du rendez-vous ne peut pas être dans le passé.');
      return;
    }

    setIsSubmitting(true);
    try {
      const appointment = await createAppointment({
        patientId: resolvedPatientId,
        scheduledAt: scheduledAt.toISOString(),
        notes: consultationType.trim(),
      });
      onCreated(appointment);
      setIsCreating(false);
      resetForm();
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_CREATE_ERROR);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="agenda-new-appointment">
      <button
        type="button"
        className="agenda-new-appointment-toggle"
        onClick={() => setIsCreating(true)}
        disabled={isCreating}
      >
        + Nouveau RDV
      </button>

      {isCreating && (
        <form className="agenda-new-appointment-panel" onSubmit={handleSubmit}>
          {patients.length > 1 && (
            <Field label="Patient">
              <PatientAutocomplete
                patients={patients}
                value={patientQuery}
                onChange={setPatientQuery}
                onSelectPatient={(patient) => setSelectedPatientId(patient.id)}
                placeholder="Rechercher un patient…"
                required
              />
            </Field>
          )}

          <Field label="Date">
            <input
              type="date"
              value={date}
              onChange={(event) => setDate(event.target.value)}
              required
            />
          </Field>

          <Field label="Heure">
            <input
              type="time"
              value={time}
              onChange={(event) => setTime(event.target.value)}
              required
            />
          </Field>

          <Field label="Type de consultation (optionnel)">
            <input
              type="text"
              value={consultationType}
              onChange={(event) => setConsultationType(event.target.value)}
              maxLength={255}
              placeholder="Ex. Contrôle de routine"
            />
          </Field>

          {error && (
            <p className="agenda-new-appointment-error" role="alert">
              {error}
            </p>
          )}

          <div className="agenda-new-appointment-actions">
            <button type="submit" disabled={isSubmitting}>
              {isSubmitting ? 'Enregistrement…' : 'Enregistrer'}
            </button>
            <button type="button" onClick={cancelPanel}>
              Annuler
            </button>
          </div>
        </form>
      )}
    </div>
  );
}

function Field({ label, children }) {
  return (
    <div className="agenda-field">
      <span className="agenda-field-label">{label}</span>
      {children}
    </div>
  );
}
