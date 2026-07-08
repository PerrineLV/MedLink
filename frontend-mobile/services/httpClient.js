import axios from 'axios';
import API_BASE_URL from '../config';

const httpClient = axios.create({
  baseURL: API_BASE_URL,
  // Plain JSON instead of API Platform's default JSON-LD: no @context/@id
  // noise to strip out on a small mobile client.
  headers: { Accept: 'application/json' },
});

export default httpClient;
