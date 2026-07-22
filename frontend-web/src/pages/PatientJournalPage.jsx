import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { CheckCircle2, Circle } from 'lucide-react';
import AppLayout from '../components/AppLayout';
import Badge from '../components/Badge';
import MedicationAutocomplete from '../components/MedicationAutocomplete';
import { createComment, fetchJournalEntries } from '../services/journalEntryService';
import { bloodPressureBand, moodBand, painBand } from '../services/journalPresentation';
import { fetchMedicationMetadata } from '../services/medicationService';
import { fetchPatients } from '../services/patientService';
import { createTreatment, fetchTreatments, scheduleLabel } from '../services/treatmentService';
import './PatientJournalPage.css';

const GENERIC_PRESCRIBE_ERROR = "Impossible d'enregistrer ce traitement, réessayez.";

const MOMENT_OPTIONS = [
  { value: 'morning', label: 'Matin' },
  { value: 'noon', label: 'Midi' },
  { value: 'evening', label: 'Soir' },
];

const FILTERS = [
  { key: 'all', label: 'Tout' },
  { key: 'week', label: 'Cette semaine' },
  { key: 'month', label: 'Ce mois' },
];

const FILTER_WINDOW_DAYS = { week: 7, month: 30 };

function withinWindow(createdAt, filterKey) {
  const days = FILTER_WINDOW_DAYS[filterKey];
  if (!days) return true;

  return Date.now() - new Date(createdAt).getTime() <= days * 86_400_000;
}

export default function PatientJournalPage() {
  const { patientId } = useParams();
  const [patientName, setPatientName] = useState(null);
  const [entries, setEntries] = useState(null);
  const [treatments, setTreatments] = useState(null);
  const [error, setError] = useState(null);
  const [filter, setFilter] = useState('all');

  const load = useCallback(async () => {
    setError(null);
    setEntries(null);
    setTreatments(null);
    setPatientName(null);

    try {
      const [patients, fetchedEntries, fetchedTreatments] = await Promise.all([
        fetchPatients(),
        fetchJournalEntries(patientId),
        fetchTreatments(patientId),
      ]);
      const patient = patients.find((candidate) => String(candidate.id) === String(patientId));
      setPatientName(patient ? `${patient.firstName} ${patient.lastName}` : null);
      setEntries(fetchedEntries);
      setTreatments(fetchedTreatments);
    } catch (requestError) {
      if (requestError.response?.status === 403) {
        setError("Vous n'avez pas accès au journal de ce patient.");
      } else {
        setError('Impossible de charger ce journal. Vérifiez votre connexion.');
      }
    }
  }, [patientId]);

  useEffect(() => {
    load();
  }, [load]);

  const handleTreatmentCreated = useCallback((treatment) => {
    setTreatments((current) => [...(current ?? []), treatment]);
  }, []);

  const filteredEntries = useMemo(
    () => (entries ?? []).filter((entry) => withinWindow(entry.createdAt, filter)),
    [entries, filter],
  );

  const handleCommentAdded = useCallback((entryId, comment) => {
    setEntries((current) =>
      current.map((entry) =>
        entry.id === entryId ? { ...entry, comments: [...entry.comments, comment] } : entry,
      ),
    );
  }, []);

  return (
    <AppLayout>
      <Link to="/patients" className="patient-journal-back">
        ‹ Retour aux patients
      </Link>

      <h1 className="patient-journal-title">{patientName ?? 'Journal du patient'}</h1>

      {error && (
        <p className="patient-journal-error" role="alert">
          {error}
        </p>
      )}

      {!error && (
        <div className="journal-layout">
          <div className="journal-column">
            <div className="patient-journal-filters" role="group" aria-label="Filtrer par période">
              {FILTERS.map((item) => (
                <button
                  key={item.key}
                  type="button"
                  className={filter === item.key ? 'active' : undefined}
                  aria-pressed={filter === item.key}
                  onClick={() => setFilter(item.key)}
                >
                  {item.label}
                </button>
              ))}
            </div>

            {entries === null && <p className="patient-journal-loading">Chargement…</p>}

            {entries !== null && filteredEntries.length === 0 && (
              <p className="patient-journal-empty">Aucune entrée sur cette période.</p>
            )}

            {filteredEntries.length > 0 && (
              <ul className="patient-journal-feed">
                {filteredEntries.map((entry) => (
                  <JournalEntryCard
                    key={entry.id}
                    entry={entry}
                    onCommentAdded={(comment) => handleCommentAdded(entry.id, comment)}
                  />
                ))}
              </ul>
            )}
          </div>

          <div className="journal-column">
            <ReadOnlyTreatmentsPanel treatments={treatments} />
            <PrescribeTreatmentPanel
              patientId={patientId}
              onTreatmentCreated={handleTreatmentCreated}
            />
          </div>
        </div>
      )}
    </AppLayout>
  );
}

function JournalEntryCard({ entry, onCommentAdded }) {
  const mood = moodBand(entry.mood);
  const pain = painBand(entry.painLevel);
  const bloodPressure = bloodPressureBand(entry.bloodPressure);
  const date = new Date(entry.createdAt).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });

  const [isCommenting, setIsCommenting] = useState(false);
  const [commentText, setCommentText] = useState('');
  const [commentError, setCommentError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const cancelComment = () => {
    setIsCommenting(false);
    setCommentError(null);
    setCommentText('');
  };

  const handleSubmitComment = async (event) => {
    event.preventDefault();
    setCommentError(null);

    if (!commentText.trim()) {
      setCommentError('Le commentaire ne peut pas être vide.');
      return;
    }

    setIsSubmitting(true);
    try {
      const comment = await createComment(entry.id, commentText.trim());
      onCommentAdded(comment);
      cancelComment();
    } catch (requestError) {
      setCommentError(
        requestError.response?.data?.detail ?? "Impossible d'enregistrer ce commentaire.",
      );
    } finally {
      setIsSubmitting(false);
    }
  };

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

      {entry.comments.length > 0 && (
        <ul className="journal-entry-comments">
          {entry.comments.map((comment) => (
            <li key={comment.id}>
              <span className="journal-entry-comment-author">Commentaire du soignant</span>
              <p>{comment.text}</p>
            </li>
          ))}
        </ul>
      )}

      {isCommenting ? (
        <form className="journal-entry-comment-form" onSubmit={handleSubmitComment}>
          <label htmlFor={`comment-${entry.id}`}>Ajouter un commentaire</label>
          <textarea
            id={`comment-${entry.id}`}
            value={commentText}
            onChange={(event) => setCommentText(event.target.value)}
            maxLength={1000}
            rows={3}
          />
          {commentError && (
            <p className="journal-entry-comment-error" role="alert">
              {commentError}
            </p>
          )}
          <div className="journal-entry-comment-actions">
            <button type="submit" disabled={isSubmitting}>
              {isSubmitting ? 'Envoi…' : 'Valider'}
            </button>
            <button type="button" onClick={cancelComment}>
              Annuler
            </button>
          </div>
        </form>
      ) : (
        <button
          type="button"
          className="journal-entry-comment-toggle"
          onClick={() => setIsCommenting(true)}
        >
          Ajouter un commentaire
        </button>
      )}
    </li>
  );
}

function ReadOnlyTreatmentsPanel({ treatments }) {
  return (
    <section className="treatments-panel">
      <h2 className="treatments-heading">Traitements du jour</h2>

      {treatments === null ? (
        <p className="patient-journal-loading">Chargement…</p>
      ) : treatments.length === 0 ? (
        <p className="patient-journal-empty">Aucun traitement en cours.</p>
      ) : (
        <ul className="treatments-list">
          {treatments.map((treatment) => {
            const allTaken = treatment.schedules.every((schedule) => schedule.todayIntake?.taken);

            return (
              <li key={treatment.id} className="treatment-card">
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
                  {treatment.schedules.map((schedule) => {
                    const { taken, takenAt } = schedule.todayIntake ?? {
                      taken: false,
                      takenAt: null,
                    };

                    return (
                      <li
                        key={schedule.id}
                        className={`treatment-row ${taken ? 'treatment-row-taken' : 'treatment-row-pending'}`}
                      >
                        {taken ? (
                          <CheckCircle2 className="treatment-icon" aria-hidden="true" />
                        ) : (
                          <Circle className="treatment-icon" aria-hidden="true" />
                        )}

                        <span className="treatment-status">
                          {taken
                            ? `Pris à ${formatTime(takenAt)}`
                            : `À prendre : ${scheduleLabel(schedule)}`}
                        </span>
                      </li>
                    );
                  })}
                </ul>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

function formatTime(isoDate) {
  return new Date(isoDate).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function formatExtractionDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR');
}

function PrescribeTreatmentPanel({ patientId, onTreatmentCreated }) {
  const [isCreating, setIsCreating] = useState(false);
  const [name, setName] = useState('');
  const [dosage, setDosage] = useState('');
  const [selectedMoments, setSelectedMoments] = useState([]);
  const [customLabels, setCustomLabels] = useState([]);
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [addedCount, setAddedCount] = useState(0);
  const [medicationExtractedAt, setMedicationExtractedAt] = useState(null);
  const nameInputRef = useRef(null);

  useEffect(() => {
    if (!isCreating) {
      return;
    }

    let cancelled = false;

    fetchMedicationMetadata()
      .then(({ extractedAt }) => {
        if (!cancelled) {
          setMedicationExtractedAt(extractedAt);
        }
      })
      .catch(() => {});

    return () => {
      cancelled = true;
    };
  }, [isCreating]);

  const resetForm = () => {
    setName('');
    setDosage('');
    setSelectedMoments([]);
    setCustomLabels([]);
    setError(null);
  };

  const cancelPanel = () => {
    setIsCreating(false);
    setAddedCount(0);
    resetForm();
  };

  const toggleMoment = (moment) => {
    setSelectedMoments((current) =>
      current.includes(moment) ? current.filter((value) => value !== moment) : [...current, moment],
    );
  };

  const addCustomLabel = () => {
    setCustomLabels((current) => [...current, '']);
  };

  const updateCustomLabel = (index, value) => {
    setCustomLabels((current) => current.map((label, i) => (i === index ? value : label)));
  };

  const removeCustomLabel = (index) => {
    setCustomLabels((current) => current.filter((_, i) => i !== index));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError(null);

    const trimmedCustomLabels = customLabels
      .map((label) => label.trim())
      .filter((label) => label !== '');
    const schedules = [
      ...selectedMoments.map((moment) => ({ moment, label: null })),
      ...trimmedCustomLabels.map((label) => ({ moment: 'custom', label })),
    ];

    if (schedules.length === 0) {
      setError('Choisissez au moins un horaire.');
      return;
    }

    setIsSubmitting(true);

    try {
      const treatment = await createTreatment({
        patientId: Number(patientId),
        name,
        dosage,
        schedules,
      });
      onTreatmentCreated(treatment);
      setAddedCount((count) => count + 1);
      resetForm();
      nameInputRef.current?.focus();
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_PRESCRIBE_ERROR);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="journal-new-entry">
      <button
        type="button"
        className="journal-new-entry-toggle"
        onClick={() => setIsCreating(true)}
        disabled={isCreating}
      >
        + Prescrire un traitement
      </button>

      {isCreating && (
        <form className="journal-new-entry-panel" onSubmit={handleSubmit}>
          {addedCount > 0 && (
            <p className="journal-new-entry-success" role="status">
              {addedCount} traitement{addedCount > 1 ? 's' : ''} ajouté{addedCount > 1 ? 's' : ''}.
              Vous pouvez en prescrire un autre ou terminer.
            </p>
          )}

          <div className="journal-field">
            <span className="journal-field-label">Nom</span>
            <MedicationAutocomplete
              ref={nameInputRef}
              value={name}
              onChange={setName}
              onSelectMedication={(medication) => {
                if (medication.suggestedDosage) {
                  setDosage(medication.suggestedDosage);
                }
              }}
              required
              ariaLabel="Nom du médicament"
            />
            <span className="journal-field-hint">
              Source : Base de données publique des médicaments (ANSM)
              {medicationExtractedAt &&
                `, extraction du ${formatExtractionDate(medicationExtractedAt)}`}
            </span>
          </div>

          <div className="journal-field">
            <span className="journal-field-label">Dosage</span>
            <input
              type="text"
              value={dosage}
              onChange={(event) => setDosage(event.target.value)}
              placeholder="5 mg"
              required
              aria-label="Dosage"
            />
          </div>

          <div className="journal-field">
            <span className="journal-field-label">Horaires (plusieurs choix possibles)</span>
            <div className="journal-pill-row" role="group" aria-label="Moments de prise">
              {MOMENT_OPTIONS.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  className={selectedMoments.includes(option.value) ? 'active' : undefined}
                  aria-pressed={selectedMoments.includes(option.value)}
                  onClick={() => toggleMoment(option.value)}
                >
                  {option.label}
                </button>
              ))}
            </div>

            {customLabels.length > 0 && (
              <div className="journal-scheduled-times">
                {customLabels.map((label, index) => (
                  <div key={index} className="journal-schedule-row">
                    <input
                      type="text"
                      value={label}
                      onChange={(event) => updateCustomLabel(index, event.target.value)}
                      placeholder="Ex. Avant le coucher"
                      aria-label={`Horaire personnalisé ${index + 1}`}
                    />
                    <button
                      type="button"
                      className="journal-scheduled-time-remove"
                      onClick={() => removeCustomLabel(index)}
                      aria-label={`Supprimer l'horaire personnalisé ${index + 1}`}
                    >
                      ✕
                    </button>
                  </div>
                ))}
              </div>
            )}

            <button type="button" className="journal-scheduled-time-add" onClick={addCustomLabel}>
              + Ajouter un horaire personnalisé
            </button>
          </div>

          {error && (
            <p className="journal-new-entry-error" role="alert">
              {error}
            </p>
          )}

          <div className="journal-new-entry-actions">
            <button type="submit" disabled={isSubmitting}>
              {isSubmitting ? 'Enregistrement…' : 'Enregistrer et ajouter un autre'}
            </button>
            <button type="button" onClick={cancelPanel}>
              {addedCount > 0 ? 'Terminer' : 'Annuler'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
