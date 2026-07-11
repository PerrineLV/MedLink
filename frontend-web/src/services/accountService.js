import httpClient from './httpClient';

function defaultExportFilename() {
  return `medlink_export_${new Date().toISOString().slice(0, 10)}.json`;
}

function extractFilename(contentDisposition) {
  const match = contentDisposition?.match(/filename="?([^"]+)"?/);
  return match?.[1] ?? defaultExportFilename();
}

function triggerBrowserDownload(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export async function fetchMe() {
  const response = await httpClient.get('/me');

  return response.data;
}

export async function changePassword({ currentPassword, newPassword }) {
  const response = await httpClient.patch('/me/password', { currentPassword, newPassword });

  return response.data;
}

export async function changeEmail({ password, newEmail }) {
  const response = await httpClient.patch('/me/email', { password, newEmail });

  return response.data;
}

export async function downloadAccountExport() {
  const response = await httpClient.get('/me/export', { responseType: 'blob' });
  const filename = extractFilename(response.headers['content-disposition']);
  triggerBrowserDownload(response.data, filename);
}

export async function deleteAccount({ password }) {
  const response = await httpClient.delete('/me', { data: { password } });

  return response.data;
}
