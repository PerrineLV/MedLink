import { useCallback, useEffect, useMemo, useState } from 'react';
import { Download, FileText } from 'lucide-react';
import AppLayout from '../components/AppLayout';
import { downloadJournalPdf, extractErrorMessage } from '../services/exportService';
import { fetchJournalEntries } from '../services/journalEntryService';
import { fetchPatients } from '../services/patientService';
import './ExportPage.css';

const PERIODS = [
  { key: '7d', label: '7 jours' },
  { key: '30d', label: '30 jours' },
  { key: 'custom', label: 'Personnalisé' },
];

const GENERIC_ERROR = 'Impossible de générer le PDF. Réessayez.';

function toISODate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function startOfDay(date) {
  const copy = new Date(date);
  copy.setHours(0, 0, 0, 0);
  return copy;
}

function addDays(date, days) {
  const copy = new Date(date);
  copy.setDate(copy.getDate() + days);
  return copy;
}

function formatDate(isoDate) {
  return new Date(`${isoDate}T00:00:00`).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function periodRange(periodKey, customFrom, customTo, todayISO) {
  if (periodKey === '7d') return { from: toISODate(addDays(new Date(), -6)), to: todayISO };
  if (periodKey === '30d') return { from: toISODate(addDays(new Date(), -29)), to: todayISO };

  return { from: customFrom, to: customTo };
}

export default function ExportPage() {
  const todayISO = useMemo(() => toISODate(new Date()), []);

  const [patients, setPatients] = useState([]);
  const [selectedPatientId, setSelectedPatientId] = useState(null);
  const [entries, setEntries] = useState(null);
  const [periodKey, setPeriodKey] = useState('7d');
  const [customFrom, setCustomFrom] = useState(todayISO);
  const [customTo, setCustomTo] = useState(todayISO);
  const [error, setError] = useState(null);
  const [isGenerating, setIsGenerating] = useState(false);

  const load = useCallback(async () => {
    setError(null);

    try {
      const fetchedPatients = await fetchPatients();
      setPatients(fetchedPatients);
      setSelectedPatientId((current) => current ?? fetchedPatients[0]?.id ?? null);
    } catch {
      setError('Impossible de charger vos données. Vérifiez votre connexion.');
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (!selectedPatientId) return;

    let cancelled = false;
    setEntries(null);

    fetchJournalEntries(selectedPatientId)
      .then((fetchedEntries) => {
        if (!cancelled) setEntries(fetchedEntries);
      })
      .catch(() => {
        if (!cancelled) setError('Impossible de charger le journal. Vérifiez votre connexion.');
      });

    return () => {
      cancelled = true;
    };
  }, [selectedPatientId]);

  const { from, to } = periodRange(periodKey, customFrom, customTo, todayISO);

  const filteredEntries = useMemo(() => {
    if (!entries || !from || !to) return [];

    const start = startOfDay(new Date(`${from}T00:00:00`)).getTime();
    const end = addDays(startOfDay(new Date(`${to}T00:00:00`)), 1).getTime() - 1;

    return entries.filter((entry) => {
      const createdAt = new Date(entry.createdAt).getTime();
      return createdAt >= start && createdAt <= end;
    });
  }, [entries, from, to]);

  const fileName = `medlink_suivi_${todayISO}.pdf`;
  const hasValidRange = Boolean(from && to && from <= to);
  const canGenerate =
    hasValidRange && entries !== null && filteredEntries.length > 0 && !isGenerating;

  const handleCustomFromChange = (value) => {
    setCustomFrom(value);
    if (customTo && value > customTo) setCustomTo(value);
  };

  const handleCustomToChange = (value) => {
    setCustomTo(value);
    if (customFrom && value < customFrom) setCustomFrom(value);
  };

  const handleGenerate = async () => {
    setError(null);
    setIsGenerating(true);

    try {
      await downloadJournalPdf({ patientId: selectedPatientId, from, to });
    } catch (requestError) {
      setError((await extractErrorMessage(requestError)) ?? GENERIC_ERROR);
    } finally {
      setIsGenerating(false);
    }
  };

  return (
    <AppLayout>
      <h1 className="export-title">Export PDF</h1>

      {error && (
        <p className="export-error" role="alert">
          {error}
        </p>
      )}

      {patients.length > 1 && (
        <Field label="Patient">
          <div className="export-pill-row" role="group" aria-label="Patient">
            {patients.map((patient) => (
              <Pill
                key={patient.id}
                label={`${patient.firstName} ${patient.lastName}`}
                selected={selectedPatientId === patient.id}
                onClick={() => setSelectedPatientId(patient.id)}
              />
            ))}
          </div>
        </Field>
      )}

      <Field label="Période">
        <div className="export-pill-row" role="group" aria-label="Période">
          {PERIODS.map((period) => (
            <Pill
              key={period.key}
              label={period.label}
              selected={periodKey === period.key}
              onClick={() => setPeriodKey(period.key)}
            />
          ))}
        </div>
      </Field>

      {periodKey === 'custom' && (
        <div className="export-date-row">
          <Field label="Du">
            <input
              type="date"
              value={customFrom}
              max={todayISO}
              onChange={(event) => handleCustomFromChange(event.target.value)}
              aria-label="Date de début"
            />
          </Field>
          <Field label="Au">
            <input
              type="date"
              value={customTo}
              max={todayISO}
              onChange={(event) => handleCustomToChange(event.target.value)}
              aria-label="Date de fin"
            />
          </Field>
        </div>
      )}

      <div
        className="export-preview"
        role="status"
        aria-label={
          entries === null
            ? 'Chargement des entrées'
            : filteredEntries.length === 0
              ? 'Aucune entrée sur cette période'
              : `${fileName}, ${filteredEntries.length} entrée${filteredEntries.length > 1 ? 's' : ''}, du ${formatDate(from)} au ${formatDate(to)}`
        }
      >
        <FileText className="export-preview-icon" aria-hidden="true" />
        <div className="export-preview-info">
          <span className="export-preview-filename">{fileName}</span>
          {entries === null ? (
            <span className="export-preview-empty">Chargement…</span>
          ) : filteredEntries.length === 0 ? (
            <span className="export-preview-empty">Aucune entrée sur cette période.</span>
          ) : (
            <span className="export-preview-summary">
              {filteredEntries.length} entrée{filteredEntries.length > 1 ? 's' : ''} · du{' '}
              {formatDate(from)} au {formatDate(to)}
            </span>
          )}
        </div>
      </div>

      <button
        type="button"
        className="export-generate-button"
        onClick={handleGenerate}
        disabled={!canGenerate}
      >
        <Download aria-hidden="true" size={18} />
        {isGenerating ? 'Génération…' : 'Générer le PDF'}
      </button>
    </AppLayout>
  );
}

function Field({ label, children }) {
  return (
    <div className="export-field">
      <span className="export-field-label">{label}</span>
      {children}
    </div>
  );
}

function Pill({ label, selected, onClick }) {
  return (
    <button
      type="button"
      className={selected ? 'active' : undefined}
      aria-pressed={selected}
      onClick={onClick}
    >
      {label}
    </button>
  );
}
