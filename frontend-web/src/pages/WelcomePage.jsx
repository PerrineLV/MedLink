import { Stethoscope } from 'lucide-react';
import { Link, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/useAuth';
import { getHomeRoute } from '../services/roles';
import logo from '../assets/medlink-logo.png';
import illustration from '../assets/welcome-illustration.png';
import './WelcomePage.css';

const HIGHLIGHTS = [
  'Centraliser vos échanges médicaux',
  'Coordination entre patient, aidant et soignant',
  'Suivi sécurisé et accessible à tout moment',
];

export default function WelcomePage() {
  const { isAuthenticated, roles } = useAuth();

  if (isAuthenticated) {
    return <Navigate to={getHomeRoute(roles)} replace />;
  }

  return (
    <main className="welcome-page">
      <div className="welcome-logo">
        <img src={logo} alt="" className="welcome-logo-image" />
        <p className="welcome-logo-wordmark">MedLink</p>
        <p className="welcome-logo-tagline">Lien Médical Simplifié</p>
      </div>

      <img
        src={illustration}
        alt="Illustration d'une soignante et d'un soignant souriants, prêts à accompagner le suivi médical partagé sur MedLink"
        className="welcome-illustration"
      />

      <div className="welcome-content">
        <h1 className="welcome-title">Vous découvrez MedLink&nbsp;?</h1>

        <ul className="welcome-highlights">
          {HIGHLIGHTS.map((label) => (
            <li key={label} className="welcome-highlight">
              <Stethoscope className="welcome-highlight-icon" aria-hidden="true" size={18} />
              <span>{label}</span>
            </li>
          ))}
        </ul>

        <div className="welcome-actions">
          <Link to="/register" className="welcome-button welcome-button-primary">
            Inscription
          </Link>
          <Link to="/login" className="welcome-button welcome-button-secondary">
            Connexion
          </Link>
        </div>
      </div>
    </main>
  );
}
