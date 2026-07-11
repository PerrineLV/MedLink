import { Navigate, Route, Routes } from 'react-router-dom';
import SessionExpiryWarning from './components/SessionExpiryWarning';
import ProtectedRoute from './components/ProtectedRoute';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import DashboardPage from './pages/DashboardPage';
import PatientsPage from './pages/PatientsPage';
import PatientJournalPage from './pages/PatientJournalPage';
import JournalPage from './pages/JournalPage';
import LiaisonsPage from './pages/LiaisonsPage';
import InvitationsPage from './pages/InvitationsPage';
import MessagingPage from './pages/MessagingPage';
import AgendaPage from './pages/AgendaPage';
import ExportPage from './pages/ExportPage';
import AccountPage from './pages/AccountPage';
import { ROLE_AIDANT, ROLE_PATIENT, ROLE_SOIGNANT } from './services/roles';

function App() {
  return (
    <>
      <SessionExpiryWarning />
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route element={<ProtectedRoute />}>
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/messages" element={<MessagingPage />} />
          <Route path="/agenda" element={<AgendaPage />} />
          <Route path="/export" element={<ExportPage />} />
          <Route path="/account" element={<AccountPage />} />
        </Route>
        <Route element={<ProtectedRoute roles={[ROLE_SOIGNANT]} />}>
          <Route path="/patients" element={<PatientsPage />} />
          <Route path="/patients/:patientId" element={<PatientJournalPage />} />
        </Route>
        <Route element={<ProtectedRoute roles={[ROLE_PATIENT, ROLE_AIDANT]} />}>
          <Route path="/journal" element={<JournalPage />} />
        </Route>
        <Route element={<ProtectedRoute roles={[ROLE_PATIENT]} />}>
          <Route path="/liaisons" element={<LiaisonsPage />} />
        </Route>
        <Route element={<ProtectedRoute roles={[ROLE_AIDANT, ROLE_SOIGNANT]} />}>
          <Route path="/invitations" element={<InvitationsPage />} />
        </Route>
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </>
  );
}

export default App;
