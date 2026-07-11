import httpClient from './httpClient';

export async function fetchUsers({ role, status } = {}) {
  const params = {};
  if (role) params.role = role;
  if (status) params.status = status;
  // No pager in the UI yet: the admin userbase is small enough at this stage
  // that a single generous page covers it (the API supports pagination for
  // when that stops being true).
  params.perPage = 100;

  const response = await httpClient.get('/admin/users', { params });

  return response.data;
}

export async function updateUserStatus(id, active) {
  const response = await httpClient.patch(`/admin/users/${id}/status`, { active });

  return response.data;
}
