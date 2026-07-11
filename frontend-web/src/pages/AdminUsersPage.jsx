import { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '../components/AppLayout';
import Badge from '../components/Badge';
import { fetchUsers, updateUserStatus } from '../services/adminService';
import { ROLE_LABELS } from '../services/roles';
import './AdminUsersPage.css';

const GENERIC_LOAD_ERROR =
  'Impossible de charger la liste des utilisateurs. Vérifiez votre connexion.';
const GENERIC_STATUS_ERROR = 'Impossible de mettre à jour ce compte, réessayez.';

const ROLE_FILTERS = [
  { key: '', label: 'Tous les rôles' },
  { key: 'patient', label: 'Patients' },
  { key: 'aidant', label: 'Aidants' },
  { key: 'soignant', label: 'Soignants' },
];

// La valeur "désactivé" (avec l'accent) est le libellé de filtre attendu tel
// quel par l'API (ML-53) : ce n'est pas un texte d'affichage à nettoyer.
const STATUS_FILTERS = [
  { key: '', label: 'Tous les statuts' },
  { key: 'actif', label: 'Actifs' },
  { key: 'désactivé', label: 'Désactivés' },
];

function formatDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('fr-FR');
}

export default function AdminUsersPage() {
  const [users, setUsers] = useState(null);
  const [loadError, setLoadError] = useState(null);
  const [roleFilter, setRoleFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [pendingDeactivation, setPendingDeactivation] = useState(null);
  const tableRef = useRef(null);

  const load = useCallback(async () => {
    setLoadError(null);

    try {
      const result = await fetchUsers({ role: roleFilter, status: statusFilter });
      setUsers(result.items);
    } catch {
      setLoadError(GENERIC_LOAD_ERROR);
    }
  }, [roleFilter, statusFilter]);

  useEffect(() => {
    load();
  }, [load]);

  const handleUserUpdated = useCallback((updatedUser) => {
    setUsers((current) =>
      (current ?? []).map((user) => (user.id === updatedUser.id ? updatedUser : user)),
    );
  }, []);

  const handleDeactivated = useCallback(
    (updatedUser) => {
      handleUserUpdated(updatedUser);
      setPendingDeactivation(null);
      tableRef.current?.focus();
    },
    [handleUserUpdated],
  );

  return (
    <AppLayout>
      <h1 className="admin-users-title">Utilisateurs</h1>

      {loadError && (
        <p className="admin-users-error" role="alert">
          {loadError}
        </p>
      )}

      <div className="admin-users-filters">
        <div role="group" aria-label="Filtrer par rôle" className="admin-users-filter-group">
          {ROLE_FILTERS.map((item) => (
            <button
              key={item.key}
              type="button"
              className={roleFilter === item.key ? 'active' : undefined}
              aria-pressed={roleFilter === item.key}
              onClick={() => setRoleFilter(item.key)}
            >
              {item.label}
            </button>
          ))}
        </div>
        <div role="group" aria-label="Filtrer par statut" className="admin-users-filter-group">
          {STATUS_FILTERS.map((item) => (
            <button
              key={item.key}
              type="button"
              className={statusFilter === item.key ? 'active' : undefined}
              aria-pressed={statusFilter === item.key}
              onClick={() => setStatusFilter(item.key)}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {pendingDeactivation && (
        <DeactivateConfirmation
          user={pendingDeactivation}
          onCancel={() => {
            setPendingDeactivation(null);
            tableRef.current?.focus();
          }}
          onDeactivated={handleDeactivated}
        />
      )}

      {users === null && !loadError && <p className="admin-users-loading">Chargement…</p>}

      {users !== null && users.length === 0 && (
        <p className="admin-users-empty">Aucun utilisateur ne correspond à ces filtres.</p>
      )}

      {users !== null && users.length > 0 && (
        <div className="admin-users-table-wrapper" ref={tableRef} tabIndex={-1}>
          <table className="admin-users-table">
            <caption className="admin-users-table-caption">
              Liste des utilisateurs MedLink, avec rôle, statut et date de création
            </caption>
            <thead>
              <tr>
                <th scope="col">E-mail</th>
                <th scope="col">Rôle</th>
                <th scope="col">Statut</th>
                <th scope="col">Créé le</th>
                <th scope="col">Action</th>
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <UserRow
                  key={user.id}
                  user={user}
                  onRequestDeactivate={() => setPendingDeactivation(user)}
                  onActivated={handleUserUpdated}
                />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}

function UserRow({ user, onRequestDeactivate, onActivated }) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const roleLabels = user.roles.map((role) => ROLE_LABELS[role] ?? role).join(', ');

  const handleActivate = async () => {
    setError(null);
    setIsSubmitting(true);

    try {
      const updatedUser = await updateUserStatus(user.id, true);
      onActivated(updatedUser);
    } catch (requestError) {
      setError(requestError.response?.data?.message ?? GENERIC_STATUS_ERROR);
      setIsSubmitting(false);
    }
  };

  return (
    <tr>
      <td>{user.email}</td>
      <td>{roleLabels}</td>
      <td>
        {user.active ? (
          <Badge level="green" label="Actif" />
        ) : (
          <Badge level="red" label="Désactivé" />
        )}
      </td>
      <td>{formatDate(user.createdAt)}</td>
      <td>
        {user.active ? (
          <button
            type="button"
            className="admin-users-deactivate-button"
            onClick={onRequestDeactivate}
            aria-label={`Désactiver le compte de ${user.email}`}
          >
            Désactiver
          </button>
        ) : (
          <button
            type="button"
            className="admin-users-activate-button"
            onClick={handleActivate}
            disabled={isSubmitting}
            aria-label={`Activer le compte de ${user.email}`}
          >
            {isSubmitting ? 'Activation…' : 'Activer'}
          </button>
        )}
        {error && (
          <p className="admin-users-row-error" role="alert">
            {error}
          </p>
        )}
      </td>
    </tr>
  );
}

function DeactivateConfirmation({ user, onCancel, onDeactivated }) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const handleConfirm = async () => {
    setError(null);
    setIsSubmitting(true);

    try {
      const updatedUser = await updateUserStatus(user.id, false);
      onDeactivated(updatedUser);
    } catch (requestError) {
      setError(requestError.response?.data?.message ?? GENERIC_STATUS_ERROR);
      setIsSubmitting(false);
    }
  };

  return (
    <div
      className="admin-users-confirm"
      role="alertdialog"
      aria-live="assertive"
      aria-label="Confirmer la désactivation"
    >
      <p>
        Désactiver le compte de {user.email} ? Il ne pourra plus se connecter tant qu&apos;il
        n&apos;est pas réactivé.
      </p>
      {error && (
        <p className="admin-users-error" role="alert">
          {error}
        </p>
      )}
      <div className="admin-users-confirm-actions">
        <button
          type="button"
          className="admin-users-confirm-button"
          onClick={handleConfirm}
          disabled={isSubmitting}
        >
          {isSubmitting ? 'Désactivation…' : 'Confirmer'}
        </button>
        <button
          type="button"
          className="admin-users-cancel-button"
          onClick={onCancel}
          disabled={isSubmitting}
        >
          Annuler
        </button>
      </div>
    </div>
  );
}
