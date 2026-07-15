import httpClient from './httpClient';

export async function login(email, password) {
  const response = await httpClient.post('/auth/login', { email, password });

  return response.data;
}

export async function register(payload) {
  const response = await httpClient.post('/auth/register', payload);

  return response.data;
}

export async function requestPasswordReset(email) {
  const response = await httpClient.post('/password-reset/request', { email, platform: 'mobile' });

  return response.data;
}

export async function confirmPasswordReset(token, newPassword) {
  const response = await httpClient.post('/password-reset/confirm', { token, newPassword });

  return response.data;
}
