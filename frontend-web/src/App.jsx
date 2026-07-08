import { Navigate, Route, Routes } from 'react-router-dom'
import SessionExpiryWarning from './components/SessionExpiryWarning'
import ProtectedRoute from './components/ProtectedRoute'
import LoginPage from './pages/LoginPage'
import DashboardPage from './pages/DashboardPage'
import PatientsPage from './pages/PatientsPage'
import PatientJournalPage from './pages/PatientJournalPage'
import JournalPage from './pages/JournalPage'
import { ROLE_AIDANT, ROLE_PATIENT, ROLE_SOIGNANT } from './services/roles'

function App() {
  return (
    <>
      <SessionExpiryWarning />
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route element={<ProtectedRoute />}>
          <Route path="/dashboard" element={<DashboardPage />} />
        </Route>
        <Route element={<ProtectedRoute roles={[ROLE_SOIGNANT]} />}>
          <Route path="/patients" element={<PatientsPage />} />
          <Route path="/patients/:patientId" element={<PatientJournalPage />} />
        </Route>
        <Route element={<ProtectedRoute roles={[ROLE_PATIENT, ROLE_AIDANT]} />}>
          <Route path="/journal" element={<JournalPage />} />
        </Route>
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </>
  )
}

export default App
