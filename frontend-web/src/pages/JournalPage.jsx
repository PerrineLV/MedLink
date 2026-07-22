import { useCallback, useEffect, useMemo, useState } from 'react';
import { CheckCircle2, Circle } from 'lucide-react';
import AppLayout from '../components/AppLayout';
import Badge from '../components/Badge';
import { useAuth } from '../contexts/useAuth';
import { createJournalEntry, fetchJournalEntries } from '../services/journalEntryService';
import { bloodPressureBand, moodBand, painBand } from '../services/journalPresentation';
import { fetchPatients } from '../services/patientService';
import { ROLE_AIDANT, ROLE_PATIENT } from '../services/roles';
import {
  fetchTreatments,
  scheduleLabel,
  toggleTreatmentIntake,
} from '../services/treatmentService';
import './JournalPage.css';

const MOOD_OPTIONS = [1, 2, 3, 4, 5];
const PAIN_OPTIONS = Array.from({ length: 11 }, (_, painLevel) => painLevel);
const BLOOD_PRESSURE_PATTERN = /^\d{1,3}$/;
const GENERIC_CREATE_ERROR = "Impossible d'enregistrer cette entrée, réessayez.";
const NO_ATTACHED_PATIENT_TEXT =
  "Aucun patient rattaché pour le moment. C'est le patient qui doit vous inviter depuis son espace pour que vous puissiez saisir des entrées pour lui.";

export default function JournalPage() {
  const { roles } = useAuth();
  const [entries, setEntries] = useState(null);
  const [treatments, setTreatments] = useState(null);
  const [patients, setPatients] = useState([]);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    setError(null);
    setEntries(null);
    setTreatments(null);

    try {
      const [fetchedEntries, fetchedPatients, fetchedTreatments] = await Promise.all([
        fetchJournalEntries(),
        fetchPatients(),
        fetchTreatments(),
      ]);
      setEntries(fetchedEntries);
      setPatients(fetchedPatients);
      setTreatments(fetchedTreatments);
    } catch (requestError) {
      if (requestError.response?.status === 403) {
        setError("Vous n'avez pas accès à ce journal.");
      } else {
        setError('Impossible de charger le journal. Vérifiez votre connexion.');
      }
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleEntryCreated = useCallback((entry) => {
    setEntries((current) => [entry, ...(current ?? [])]);
  }, []);

  const showPatientName = useMemo(
    () =>
      new Set([...(entries ?? []), ...(treatments ?? [])].map((item) => item.patientId)).size > 1,
    [entries, treatments],
  );

  const isDataLoaded = entries !== null;
  const hasNoAttachedPatient =
    isDataLoaded &&
    roles.includes(ROLE_AIDANT) &&
    !roles.includes(ROLE_PATIENT) &&
    patients.length === 0;

  const entriesByPatientId = useMemo(() => groupByPatientId(entries ?? []), [entries]);
  const treatmentsByPatientId = useMemo(() => groupByPatientId(treatments ?? []), [treatments]);

  const applyScheduleIntake = useCallback((treatmentId, scheduleId, todayIntake) => {
    setTreatments((current) =>
      current.map((treatment) =>
        treatment.id !== treatmentId
          ? treatment
          : {
              ...treatment,
              schedules: treatment.schedules.map((schedule) =>
                schedule.id === scheduleId ? { ...schedule, todayIntake } : schedule,
              ),
            },
      ),
    );
  }, []);

  const handleToggleIntake = useCallback(
    (treatment, schedule) => {
      const previousIntake = schedule.todayIntake;
      const optimisticIntake = {
        ...previousIntake,
        taken: !previousIntake.taken,
        takenAt: previousIntake.taken ? null : new Date().toISOString(),
      };

      applyScheduleIntake(treatment.id, schedule.id, optimisticIntake);

      toggleTreatmentIntake(previousIntake.id)
        .then((updatedIntake) => {
          applyScheduleIntake(treatment.id, schedule.id, updatedIntake);
        })
        .catch(() => {
          applyScheduleIntake(treatment.id, schedule.id, previousIntake);
          setError('Impossible de mettre à jour ce traitement. Réessayez.');
        });
    },
    [applyScheduleIntake],
  );

  return (
    <AppLayout>
      {error && (
        <p className="journal-error" role="alert">
          {error}
        </p>
      )}

      {!error && (
        <>
          {hasNoAttachedPatient ? (
            <p className="journal-empty" role="status">
              {NO_ATTACHED_PATIENT_TEXT}
            </p>
          ) : (
            <NewEntryPanel patients={patients} onEntryCreated={handleEntryCreated} />
          )}

          {entries === null ? (
            <p className="journal-loading">Chargement…</p>
          ) : showPatientName ? (
            <PatientGroupedJournal
              patients={patients}
              entriesByPatientId={entriesByPatientId}
              treatmentsByPatientId={treatmentsByPatientId}
              onToggle={handleToggleIntake}
            />
          ) : (
            <div className="journal-layout">
              <div className="journal-column">
                {entries.length === 0 ? (
                  <p className="journal-empty">Aucune entrée pour le moment.</p>
                ) : (
                  <ul className="journal-feed">
                    {entries.map((entry) => (
                      <JournalEntryCard key={entry.id} entry={entry} />
                    ))}
                  </ul>
                )}
              </div>

              <div className="journal-column">
                <TreatmentsPanel treatments={treatments} onToggle={handleToggleIntake} />
              </div>
            </div>
          )}
        </>
      )}
    </AppLayout>
  );
}

function groupByPatientId(items) {
  return items.reduce((groups, item) => {
    const group = groups[item.patientId] ?? [];
    group.push(item);
    groups[item.patientId] = group;

    return groups;
  }, {});
}

function PatientGroupedJournal({ patients, entriesByPatientId, treatmentsByPatientId, onToggle }) {
  const patientsWithData = patients.filter(
    (patient) =>
      entriesByPatientId[patient.id]?.length > 0 || treatmentsByPatientId[patient.id]?.length > 0,
  );

  if (patientsWithData.length === 0) {
    return <p className="journal-empty">Aucune entrée pour le moment.</p>;
  }

  return (
    <div className="journal-patient-groups">
      {patientsWithData.map((patient) => {
        const patientEntries = entriesByPatientId[patient.id] ?? [];
        const patientTreatments = treatmentsByPatientId[patient.id] ?? [];

        return (
          <section key={patient.id} className="journal-patient-group">
            <h2 className="journal-patient-group-heading">
              {patient.firstName} {patient.lastName}
            </h2>

            <div className="journal-layout">
              <div className="journal-column">
                {patientEntries.length === 0 ? (
                  <p className="journal-empty">Aucune entrée pour le moment.</p>
                ) : (
                  <ul className="journal-feed">
                    {patientEntries.map((entry) => (
                      <JournalEntryCard key={entry.id} entry={entry} />
                    ))}
                  </ul>
                )}
              </div>

              <div className="journal-column">
                <TreatmentsPanel treatments={patientTreatments} onToggle={onToggle} />
              </div>
            </div>
          </section>
        );
      })}
    </div>
  );
}

function TreatmentsPanel({ treatments, onToggle }) {
  return (
    <section className="treatments-panel">
      <h2 className="treatments-heading">Traitements du jour</h2>

      {treatments.length === 0 ? (
        <p className="journal-empty">Aucun traitement en cours.</p>
      ) : (
        <ul className="treatments-list">
          {treatments.map((treatment) => (
            <TreatmentCard key={treatment.id} treatment={treatment} onToggle={onToggle} />
          ))}
        </ul>
      )}
    </section>
  );
}

function TreatmentCard({ treatment, onToggle }) {
  const allTaken = treatment.schedules.every((schedule) => schedule.todayIntake?.taken);

  return (
    <li className="treatment-card">
      <div
        className={`treatment-card-header ${allTaken ? 'treatment-row-taken' : 'treatment-row-pending'}`}
      >
        <span
          role="img"
          aria-label={
            allTaken
              ? 'Tous les horaires du jour sont pris'
              : 'Certains horaires du jour restent à prendre'
          }
        >
          {allTaken ? (
            <CheckCircle2 className="treatment-icon" aria-hidden="true" />
          ) : (
            <Circle className="treatment-icon" aria-hidden="true" />
          )}
        </span>

        <span className="treatment-info">
          <span className="treatment-name">
            {treatment.name} · {treatment.dosage}
          </span>
        </span>
      </div>

      <ul className="treatment-schedule-list">
        {treatment.schedules.map((schedule) => (
          <TreatmentScheduleRow
            key={schedule.id}
            treatment={treatment}
            schedule={schedule}
            onToggle={onToggle}
          />
        ))}
      </ul>
    </li>
  );
}

function TreatmentScheduleRow({ treatment, schedule, onToggle }) {
  const { taken, takenAt } = schedule.todayIntake ?? { taken: false, takenAt: null };
  const label = taken
    ? `${treatment.name} ${treatment.dosage}, pris à ${formatTime(takenAt)}`
    : `${treatment.name} ${treatment.dosage}, à prendre : ${scheduleLabel(schedule)} — appuyer pour marquer comme pris`;

  return (
    <li>
      <button
        type="button"
        className={`treatment-row ${taken ? 'treatment-row-taken' : 'treatment-row-pending'}`}
        onClick={() => onToggle(treatment, schedule)}
        aria-label={label}
      >
        {taken ? (
          <CheckCircle2 className="treatment-icon" aria-hidden="true" />
        ) : (
          <Circle className="treatment-icon" aria-hidden="true" />
        )}

        <span className="treatment-status">
          {taken ? `Pris à ${formatTime(takenAt)}` : `À prendre : ${scheduleLabel(schedule)}`}
        </span>
      </button>
    </li>
  );
}

function formatTime(isoDate) {
  return new Date(isoDate).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function NewEntryPanel({ patients, onEntryCreated }) {
  const [isCreating, setIsCreating] = useState(false);
  const [selectedPatientId, setSelectedPatientId] = useState(null);
  const [mood, setMood] = useState(3);
  const [painLevel, setPainLevel] = useState(0);
  const [systolic, setSystolic] = useState('');
  const [diastolic, setDiastolic] = useState('');
  const [note, setNote] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const resolvedPatientId = selectedPatientId ?? patients[0]?.id ?? null;

  const openPanel = () => {
    setIsCreating(true);
  };

  const resetForm = () => {
    setSelectedPatientId(null);
    setMood(3);
    setPainLevel(0);
    setSystolic('');
    setDiastolic('');
    setNote('');
    setError(null);
  };

  const cancelPanel = () => {
    setIsCreating(false);
    resetForm();
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);

    if (!BLOOD_PRESSURE_PATTERN.test(systolic) || !BLOOD_PRESSURE_PATTERN.test(diastolic)) {
      setError('La tension doit être au format "120/80".');
      return;
    }

    setIsSubmitting(true);
    try {
      const entry = await createJournalEntry({
        patientId: resolvedPatientId,
        mood,
        painLevel,
        bloodPressure: `${systolic}/${diastolic}`,
        note: note.trim(),
      });
      onEntryCreated(entry);
      setIsCreating(false);
      resetForm();
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_CREATE_ERROR);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="journal-new-entry">
      <button
        type="button"
        className="journal-new-entry-toggle"
        onClick={openPanel}
        disabled={isCreating}
      >
        + Nouvelle entrée
      </button>

      {isCreating && (
        <form className="journal-new-entry-panel" onSubmit={handleSubmit}>
          {patients.length > 1 && (
            <Field label="Patient">
              <div className="journal-pill-row" role="group" aria-label="Patient">
                {patients.map((patient) => (
                  <Pill
                    key={patient.id}
                    label={`${patient.firstName} ${patient.lastName}`}
                    selected={resolvedPatientId === patient.id}
                    onClick={() => setSelectedPatientId(patient.id)}
                  />
                ))}
              </div>
            </Field>
          )}

          <Field label="Humeur">
            <div className="journal-pill-row" role="group" aria-label="Humeur">
              {MOOD_OPTIONS.map((value) => (
                <Pill
                  key={value}
                  label={String(value)}
                  selected={mood === value}
                  onClick={() => setMood(value)}
                  ariaLabel={`Humeur : ${value} sur 5`}
                />
              ))}
            </div>
          </Field>

          <Field label="Douleur">
            <div
              className="journal-pill-row journal-pill-row-scroll"
              role="group"
              aria-label="Douleur"
            >
              {PAIN_OPTIONS.map((value) => (
                <Pill
                  key={value}
                  label={String(value)}
                  selected={painLevel === value}
                  onClick={() => setPainLevel(value)}
                  ariaLabel={`Douleur : ${value} sur 10`}
                />
              ))}
            </div>
          </Field>

          <Field label="Tension artérielle">
            <div className="journal-blood-pressure-row">
              <input
                type="text"
                inputMode="numeric"
                maxLength={3}
                placeholder="120"
                value={systolic}
                onChange={(event) => setSystolic(event.target.value)}
                aria-label="Tension systolique"
              />
              <span aria-hidden="true">/</span>
              <input
                type="text"
                inputMode="numeric"
                maxLength={3}
                placeholder="80"
                value={diastolic}
                onChange={(event) => setDiastolic(event.target.value)}
                aria-label="Tension diastolique"
              />
            </div>
          </Field>

          <Field label="Note (optionnelle)">
            <textarea
              value={note}
              onChange={(event) => setNote(event.target.value)}
              maxLength={1000}
              rows={3}
              placeholder="Précisions sur la journée…"
              aria-label="Note (optionnelle)"
            />
          </Field>

          {error && (
            <p className="journal-new-entry-error" role="alert">
              {error}
            </p>
          )}

          <div className="journal-new-entry-actions">
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
    <div className="journal-field">
      <span className="journal-field-label">{label}</span>
      {children}
    </div>
  );
}

function Pill({ label, selected, onClick, ariaLabel }) {
  return (
    <button
      type="button"
      className={selected ? 'active' : undefined}
      aria-pressed={selected}
      aria-label={ariaLabel}
      onClick={onClick}
    >
      {label}
    </button>
  );
}

function JournalEntryCard({ entry }) {
  const mood = moodBand(entry.mood);
  const pain = painBand(entry.painLevel);
  const bloodPressure = bloodPressureBand(entry.bloodPressure);
  const date = useMemo(
    () =>
      new Date(entry.createdAt).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      }),
    [entry.createdAt],
  );

  return (
    <li className="journal-entry-card">
      <div className="journal-entry-header">
        <span className="journal-entry-date">{date}</span>
        {entry.enteredByCaregiver && (
          <span className="journal-entry-caregiver-tag">Saisie par l'aidant</span>
        )}
      </div>

      <div className="journal-entry-metrics">
        <div className="journal-entry-metric">
          <span className="journal-entry-metric-label">Humeur</span>
          <Badge level={mood.level} label={mood.label} />
        </div>
        <div className="journal-entry-metric">
          <span className="journal-entry-metric-label">Douleur {entry.painLevel}/10</span>
          <Badge level={pain.level} label={pain.label} />
        </div>
        <div className="journal-entry-metric">
          <span className="journal-entry-metric-label">Tension {entry.bloodPressure}</span>
          <Badge level={bloodPressure.level} label={bloodPressure.label} />
        </div>
      </div>

      {entry.note && <p className="journal-entry-note">{entry.note}</p>}
    </li>
  );
}
