import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import httpClient from './httpClient';

function defaultExportFilename() {
  return `medlink_export_${new Date().toISOString().slice(0, 10)}.json`;
}

function extractFilename(contentDisposition) {
  const match = contentDisposition?.match(/filename="?([^"]+)"?/);
  return match?.[1] ?? defaultExportFilename();
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
  const response = await httpClient.get('/me/export');

  const filename = extractFilename(response.headers['content-disposition']);
  const file = new File(Paths.document, filename);
  file.write(JSON.stringify(response.data));

  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(file.uri, {
      mimeType: 'application/json',
      dialogTitle: 'Export de mes données MedLink',
    });
  }

  return file;
}

export async function deleteAccount({ password }) {
  const response = await httpClient.delete('/me', { data: { password } });

  return response.data;
}
