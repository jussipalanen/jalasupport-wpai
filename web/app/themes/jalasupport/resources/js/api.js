/**
 * Minimal client for the jalasupport/v1 REST API (cookie auth + REST nonce).
 */
const configElement = document.getElementById('jalasupport-config');

export const config = configElement ? JSON.parse(configElement.textContent) : {
  restUrl: '/wp-json/jalasupport/v1/',
  nonce: '',
  appUrl: '/',
  canDelete: false,
  options: { statuses: {}, priorities: {}, categories: {} },
};

export class ApiError extends Error {
  constructor(message, status, code, errors = {}) {
    super(message);
    this.status = status;
    this.code = code;
    this.errors = errors;
  }
}

export async function api(path, { method = 'GET', query, body } = {}) {
  const url = new URL(path.replace(/^\//, ''), config.restUrl);

  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      url.searchParams.set(key, value);
    }
  });

  let response;

  try {
    response = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-WP-Nonce': config.nonce,
        ...(body ? { 'Content-Type': 'application/json' } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError('Network error. Check your connection and try again.', 0, 'network_error');
  }

  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(
      payload?.message ?? `Request failed (${response.status}).`,
      response.status,
      payload?.code ?? 'http_error',
      payload?.data?.errors ?? {},
    );
  }

  return payload;
}
