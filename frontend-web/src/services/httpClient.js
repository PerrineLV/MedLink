import axios from 'axios'

const httpClient = axios.create({
  baseURL: '/api',
  // Plain JSON instead of API Platform's default JSON-LD: no @context/@id
  // noise to strip out on the client.
  headers: { Accept: 'application/json' },
})

export default httpClient
