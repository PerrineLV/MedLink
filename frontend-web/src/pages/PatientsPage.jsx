import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import AppLayout from '../components/AppLayout'
import Badge from '../components/Badge'
import { fetchJournalEntries } from '../services/journalEntryService'
import { patientStatusBand } from '../services/journalPresentation'
import { fetchPatients } from '../services/patientService'
import './PatientsPage.css'

function initials(firstName, lastName) {
  return `${firstName[0] ?? ''}${lastName[0] ?? ''}`.toUpperCase()
}

export default function PatientsPage() {
  const navigate = useNavigate()
  const [patients, setPatients] = useState(null)
  const [error, setError] = useState(null)

  const load = useCallback(async () => {
    setError(null)

    try {
      const fetchedPatients = await fetchPatients()
      const withEntries = await Promise.all(
        fetchedPatients.map(async (patient) => ({
          ...patient,
          entries: await fetchJournalEntries(patient.id),
        })),
      )
      setPatients(withEntries)
    } catch {
      setError('Impossible de charger la liste des patients. Vérifiez votre connexion.')
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  return (
    <AppLayout>
      <h1 className="patients-title">Mes patients</h1>

      {error && (
        <p className="patients-error" role="alert">
          {error}
        </p>
      )}

      {patients === null && !error && <p className="patients-loading">Chargement…</p>}

      {patients && patients.length === 0 && (
        <p className="patients-empty">Aucun patient rattaché pour le moment.</p>
      )}

      {patients && patients.length > 0 && (
        <ul className="patient-list">
          {patients.map((patient) => {
            const status = patientStatusBand(patient.entries)
            const [lastEntry] = patient.entries

            return (
              <li key={patient.id}>
                <button
                  type="button"
                  className="patient-card"
                  onClick={() => navigate(`/patients/${patient.id}`)}
                >
                  <span className="patient-avatar" aria-hidden="true">
                    {initials(patient.firstName, patient.lastName)}
                  </span>
                  <span className="patient-info">
                    <span className="patient-name">
                      {patient.firstName} {patient.lastName}
                    </span>
                    <span className="patient-last-entry">
                      {lastEntry
                        ? `Dernière entrée : ${new Date(lastEntry.createdAt).toLocaleDateString('fr-FR')}`
                        : 'Aucune entrée'}
                    </span>
                  </span>
                  <Badge level={status.level} label={status.label} />
                </button>
              </li>
            )
          })}
        </ul>
      )}
    </AppLayout>
  )
}
