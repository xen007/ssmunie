import axios from 'axios';
import config from './config';

/**
 * Client HTTP unique.
 * - baseURL relative (/api), withCredentials (cookie ssm_sid / __Host-ssm_sid)
 * - Intercepteur 401 : redirige /login SAUF pour auth.php et check_session.php
 *   (gérés localement par l'AuthProvider → toast / polling).
 */
const api = axios.create({
  baseURL: config.apiBaseUrl,
  withCredentials: true,
  headers: { 'Content-Type': 'application/json' },
});

const REDIRECT_EXCLUDED = ['/auth.php', '/check_session.php'];

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const url = error.config?.url || '';
    const excluded = REDIRECT_EXCLUDED.some((fragment) => url.includes(fragment));
    if (status === 401 && !excluded && window.location.pathname !== '/login') {
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
