import { useEffect, useState } from 'react';
import AppLayout from '../components/AppLayout';
import Badge from '../components/Badge';
import { fetchHealthSummary } from '../services/adminService';
import './AdminSupervisionPage.css';

const GENERIC_LOAD_ERROR =
  'Impossible de charger la supervision technique. Vérifiez votre connexion.';

export default function AdminSupervisionPage() {
  const [summary, setSummary] = useState(null);
  const [loadError, setLoadError] = useState(null);

  // ML-168 : forme commune de chargement au montage. La garde `cancelled`
  // évite de poser l'état si l'utilisateur a quitté la page avant la fin de
  // la requête.
  useEffect(() => {
    let cancelled = false;

    (async () => {
      setLoadError(null);

      try {
        const fetched = await fetchHealthSummary();
        if (!cancelled) {
          setSummary(fetched);
        }
      } catch {
        if (!cancelled) {
          setLoadError(GENERIC_LOAD_ERROR);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <AppLayout>
      <h1 className="admin-supervision-title">Supervision technique</h1>

      {loadError && (
        <p className="admin-supervision-error" role="alert">
          {loadError}
        </p>
      )}

      {summary === null && !loadError && <p className="admin-supervision-loading">Chargement…</p>}

      {summary && (
        <div className="admin-supervision-sections">
          <section aria-labelledby="admin-supervision-technique-heading">
            <h2
              id="admin-supervision-technique-heading"
              className="admin-supervision-section-heading"
            >
              État technique
            </h2>
            <div className="admin-supervision-grid">
              <HealthStatusCard status={summary.health.status} />
              <StatCard
                label="Connexions échouées (24 h)"
                value={summary.failedLoginAttempts.last24h}
              />
            </div>
          </section>

          <section aria-labelledby="admin-supervision-comptes-heading">
            <h2
              id="admin-supervision-comptes-heading"
              className="admin-supervision-section-heading"
            >
              Comptes actifs
            </h2>
            <div className="admin-supervision-grid">
              <StatCard label="Patients actifs" value={summary.activeAccountsByRole.patient} />
              <StatCard label="Aidants actifs" value={summary.activeAccountsByRole.aidant} />
              <StatCard label="Soignants actifs" value={summary.activeAccountsByRole.soignant} />
            </div>
          </section>
        </div>
      )}
    </AppLayout>
  );
}

function HealthStatusCard({ status }) {
  const isOk = status === 'ok';

  return (
    <section className="admin-supervision-card" aria-labelledby="admin-supervision-health-heading">
      <h2 id="admin-supervision-health-heading" className="admin-supervision-card-label">
        Base de données
      </h2>
      {isOk ? (
        <Badge level="green" label="Opérationnelle" />
      ) : (
        <Badge level="red" label="Indisponible" />
      )}
    </section>
  );
}

function StatCard({ label, value }) {
  return (
    <section className="admin-supervision-card">
      <h2 className="admin-supervision-card-label">{label}</h2>
      <p className="admin-supervision-card-value">{value}</p>
    </section>
  );
}
