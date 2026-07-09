import httpClient from './httpClient';

function defaultFilename() {
  return `medlink_suivi_${new Date().toISOString().slice(0, 10)}.pdf`;
}

function extractFilename(contentDisposition) {
  const match = contentDisposition?.match(/filename="?([^"]+)"?/);
  return match?.[1] ?? defaultFilename();
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

// Le backend renvoie un corps JSON (Content-Type application/json) meme en
// erreur, mais la requete est faite avec responseType 'blob' (pour pouvoir
// aussi recevoir un PDF binaire côté succes) : il faut donc relire le blob
// d'erreur comme du texte pour retrouver le message explicite.
export async function extractErrorMessage(error) {
  const data = error.response?.data;
  if (data instanceof Blob) {
    try {
      const text = await data.text();
      return JSON.parse(text).detail ?? null;
    } catch {
      return null;
    }
  }

  return error.response?.data?.detail ?? null;
}

export async function downloadJournalPdf({ patientId, from, to }) {
  const response = await httpClient.get('/export/pdf', {
    params: { from, to, patient: patientId },
    responseType: 'blob',
  });

  const filename = extractFilename(response.headers['content-disposition']);
  triggerBrowserDownload(response.data, filename);
}
