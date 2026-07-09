import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import httpClient from './httpClient';

export function toISODate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function defaultFilename() {
  return `medlink_suivi_${toISODate(new Date())}.pdf`;
}

function extractFilename(contentDisposition) {
  const match = contentDisposition?.match(/filename="?([^"]+)"?/);
  return match?.[1] ?? defaultFilename();
}

// Le backend renvoie un corps JSON (Content-Type application/json) meme en
// erreur, mais la requete est faite avec responseType 'arraybuffer' (pour
// pouvoir aussi recevoir un PDF binaire côté succes) : il faut donc decoder
// le buffer d'erreur manuellement pour retrouver le message explicite.
export function extractErrorMessage(error) {
  const data = error.response?.data;
  if (data instanceof ArrayBuffer) {
    try {
      const text = new TextDecoder().decode(data);
      return JSON.parse(text).detail ?? null;
    } catch {
      return null;
    }
  }

  return error.response?.data?.detail ?? null;
}

export async function downloadAndShareJournalPdf({ patientId, from, to }) {
  const response = await httpClient.get('/export/pdf', {
    params: { from, to, patient: patientId },
    responseType: 'arraybuffer',
  });

  const filename = extractFilename(response.headers['content-disposition']);
  const file = new File(Paths.document, filename);
  file.write(new Uint8Array(response.data));

  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(file.uri, {
      mimeType: 'application/pdf',
      dialogTitle: 'Export du suivi MedLink',
    });
  }

  return file;
}
