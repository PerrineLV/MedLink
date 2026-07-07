import { useCallback, useEffect, useMemo, useState } from 'react'
import { useAuth } from '../contexts/AuthContext'
import Badge from '../components/Badge'
import { createJournalEntry, fetchJournalEntries } from '../services/journalEntryService'
import { bloodPressureBand, moodBand, painBand } from '../services/journalPresentation'
import { fetchPatients } from '../services/patientService'
import { ROLE_LABELS, getPrimaryRole } from '../services/roles'
import './JournalPage.css'

const MOOD_OPTIONS = [1, 2, 3, 4, 5]
const PAIN_OPTIONS = Array.from({ length: 11 }, (_, painLevel) => painLevel)
const BLOOD_PRESSURE_PATTERN = /^\d{1,3}$/
const GENERIC_CREATE_ERROR = "Impossible d'enregistrer cette entrée, réessayez."

const NAV_ITEMS = [
  { key: 'journal', label: 'Journal' },
  { key: 'traitements', label: 'Traitements' },
  { key: 'messagerie', label: 'Messagerie' },
  { key: 'rdv', label: 'Rendez-vous' },
  { key: 'export', label: 'Export PDF' },
]

function notifyComingSoon() {
  window.alert('Cette fonctionnalité arrive dans une prochaine version de MedLink.')
}

export default function JournalPage() {
  const { roles, firstName } = useAuth()
  const primaryRole = getPrimaryRole(roles)
  const displayName = firstName ?? (primaryRole ? ROLE_LABELS[primaryRole] : 'Utilisateur')

  const [entries, setEntries] = useState(null)
  const [patients, setPatients] = useState([])
  const [error, setError] = useState(null)

  const load = useCallback(async () => {
    setError(null)
    setEntries(null)

    try {
      const [fetchedEntries, fetchedPatients] = await Promise.all([fetchJournalEntries(), fetchPatients()])
      setEntries(fetchedEntries)
      setPatients(fetchedPatients)
    } catch (requestError) {
      if (requestError.response?.status === 403) {
        setError("Vous n'avez pas accès à ce journal.")
      } else {
        setError('Impossible de charger le journal. Vérifiez votre connexion.')
      }
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const handleEntryCreated = useCallback((entry) => {
    setEntries((current) => [entry, ...(current ?? [])])
  }, [])

  return (
    <div className="journal-page">
      <a href="#journal-main" className="journal-skip-link">
        Aller au contenu principal
      </a>

      <header className="journal-header">
        <div className="journal-header-brand">
          <span className="journal-header-logo" aria-hidden="true">
            🛡️
          </span>
          <div>
            <p className="journal-header-title">MedLink</p>
            <p className="journal-header-name">{displayName}</p>
          </div>
        </div>
        <span
          className="journal-header-lock"
          role="img"
          aria-label="Connexion sécurisée"
          title="Connexion sécurisée"
        >
          🔒
        </span>
      </header>

      <p className="journal-security-banner">
        Données chiffrées — accessibles uniquement à l'équipe soignante désignée
      </p>

      <nav className="journal-nav" aria-label="Navigation principale">
        {NAV_ITEMS.map((item) => (
          <button
            key={item.key}
            type="button"
            className={item.key === 'journal' ? 'active' : undefined}
            aria-current={item.key === 'journal' ? 'page' : undefined}
            onClick={item.key === 'journal' ? undefined : notifyComingSoon}
          >
            {item.label}
          </button>
        ))}
      </nav>

      <main id="journal-main" className="journal-main">
        <NewEntryPanel patients={patients} onEntryCreated={handleEntryCreated} />

        {error && (
          <p className="journal-error" role="alert">
            {error}
          </p>
        )}

        {!error && entries === null && <p className="journal-loading">Chargement…</p>}

        {!error && entries !== null && entries.length === 0 && (
          <p className="journal-empty">Aucune entrée pour le moment.</p>
        )}

        {!error && entries !== null && entries.length > 0 && (
          <ul className="journal-feed">
            {entries.map((entry) => (
              <JournalEntryCard key={entry.id} entry={entry} />
            ))}
          </ul>
        )}
      </main>
    </div>
  )
}

function NewEntryPanel({ patients, onEntryCreated }) {
  const [isCreating, setIsCreating] = useState(false)
  const [selectedPatientId, setSelectedPatientId] = useState(null)
  const [mood, setMood] = useState(3)
  const [painLevel, setPainLevel] = useState(0)
  const [systolic, setSystolic] = useState('')
  const [diastolic, setDiastolic] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const resolvedPatientId = selectedPatientId ?? patients[0]?.id ?? null

  const openPanel = () => {
    setIsCreating(true)
  }

  const resetForm = () => {
    setSelectedPatientId(null)
    setMood(3)
    setPainLevel(0)
    setSystolic('')
    setDiastolic('')
    setNote('')
    setError(null)
  }

  const cancelPanel = () => {
    setIsCreating(false)
    resetForm()
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError(null)

    if (!BLOOD_PRESSURE_PATTERN.test(systolic) || !BLOOD_PRESSURE_PATTERN.test(diastolic)) {
      setError('La tension doit être au format "120/80".')
      return
    }

    setIsSubmitting(true)
    try {
      const entry = await createJournalEntry({
        patientId: resolvedPatientId,
        mood,
        painLevel,
        bloodPressure: `${systolic}/${diastolic}`,
        note: note.trim(),
      })
      onEntryCreated(entry)
      setIsCreating(false)
      resetForm()
    } catch (requestError) {
      setError(requestError.response?.data?.detail ?? GENERIC_CREATE_ERROR)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="journal-new-entry">
      <button type="button" className="journal-new-entry-toggle" onClick={openPanel} disabled={isCreating}>
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
            <div className="journal-pill-row journal-pill-row-scroll" role="group" aria-label="Douleur">
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
  )
}

function Field({ label, children }) {
  return (
    <div className="journal-field">
      <span className="journal-field-label">{label}</span>
      {children}
    </div>
  )
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
  )
}

function JournalEntryCard({ entry }) {
  const mood = moodBand(entry.mood)
  const pain = painBand(entry.painLevel)
  const bloodPressure = bloodPressureBand(entry.bloodPressure)
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
  )

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
    </li>
  )
}
