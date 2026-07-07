import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { getHomeRoute } from '../services/roles'

export default function ProtectedRoute({ roles }) {
  const { isAuthenticated, roles: userRoles } = useAuth()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (roles && !roles.some((role) => userRoles.includes(role))) {
    return <Navigate to={getHomeRoute(userRoles)} replace />
  }

  return <Outlet />
}
