import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import AppLayout from '../components/AppLayout'
import Badge from '../components/Badge'
import { createComment, fetchJournalEntries } from '../services/journalEntryService'
import { bloodPressureBand, moodBand, painBand } from '../services/journalPresentation'
import { fetchPatients } from '../services/patientService'
import './PatientJournalPage.css'

const FILTERS = [
  { key: 'all', label: 'Tout' },
  { key: 'week', label: 'Cette semaine' },
  { key: 'month', label: 'Ce mois' },
]

const FILTER_WINDOW_DAYS = { week: 7, month: 30 }

function withinWindow(createdAt, filterKey) {
  const days = FILTER_WINDOW_DAYS[filterKey]
  if (!days) return true

  return Date.now() - new Date(createdAt).getTime() <= days * 86_400_000
}

export default function PatientJournalPage() {
  const { patientId } = useParams()
  const [patientName, setPatientName] = useState(null)
  const [entries, setEntries] = useState(null)
  const [error, setError] = useState(null)
  const [filter, setFilter] = useState('all')

  const load = useCallback(async () => {
    setError(null)
    setEntries(null)
    setPatientName(null)

    try {
      const [patients, fetchedEntries] = await Promise.all([fetchPatients(), fetchJournalEntries(patientId)])
      const patient = patients.find((candidate) => String(candidate.id) === String(patientId))
      setPatientName(patient ? `${patient.firstName} ${patient.lastName}` : null)
      setEntries(fetchedEntries)
    } catch (requestError) {
      if (requestError.response?.status === 403) {
        setError("Vous n'avez pas accès au journal de ce patient.")
      } else {
        setError('Impossible de charger ce journal. Vérifiez votre connexion.')
      }
    }
  }, [patientId])

  useEffect(() => {
    load()
  }, [load])

  const filteredEntries = useMemo(
    () => (entries ?? []).filter((entry) => withinWindow(entry.createdAt, filter)),
    [entries, filter],
  )

  const handleCommentAdded = useCallback((entryId, comment) => {
    setEntries((current) =>
      current.map((entry) =>
        entry.id === entryId ? { ...entry, comments: [...entry.comments, comment] } : entry,
      ),
    )
  }, [])

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
      )}

      {!error && entries === null && <p className="patient-journal-loading">Chargement…</p>}

      {!error && entries !== null && filteredEntries.length === 0 && (
        <p className="patient-journal-empty">Aucune entrée sur cette période.</p>
      )}

      {!error && filteredEntries.length > 0 && (
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
    </AppLayout>
  )
}

function JournalEntryCard({ entry, onCommentAdded }) {
  const mood = moodBand(entry.mood)
  const pain = painBand(entry.painLevel)
  const bloodPressure = bloodPressureBand(entry.bloodPressure)
  const date = new Date(entry.createdAt).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

  const [isCommenting, setIsCommenting] = useState(false)
  const [commentText, setCommentText] = useState('')
  const [commentError, setCommentError] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const cancelComment = () => {
    setIsCommenting(false)
    setCommentError(null)
    setCommentText('')
  }

  const handleSubmitComment = async (event) => {
    event.preventDefault()
    setCommentError(null)

    if (!commentText.trim()) {
      setCommentError('Le commentaire ne peut pas être vide.')
      return
    }

    setIsSubmitting(true)
    try {
      const comment = await createComment(entry.id, commentText.trim())
      onCommentAdded(comment)
      cancelComment()
    } catch (requestError) {
      setCommentError(requestError.response?.data?.detail ?? "Impossible d'enregistrer ce commentaire.")
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <li className="journal-entry-card">
      <div className="journal-entry-header">
        <span className="journal-entry-date">{date}</span>
        {entry.enteredByCaregiver && <span className="journal-entry-caregiver-tag">Saisie par l'aidant</span>}
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
        <button type="button" className="journal-entry-comment-toggle" onClick={() => setIsCommenting(true)}>
          Ajouter un commentaire
        </button>
      )}
    </li>
  )
}
