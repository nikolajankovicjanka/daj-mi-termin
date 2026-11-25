export const apiBase = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '');
export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(apiBase + path, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });
  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    const errors = Object.values(data.errors || {}).flat();
    throw new Error(String(errors[0] || data.message || 'Zahtjev nije uspio. Pokušajte ponovo.'));
  }
  return response.status === 204 ? (undefined as T) : response.json();
}
export function post<T>(path: string, body: object) {
  return api<T>(path, { method: 'POST', body: JSON.stringify(body) });
}
export type Salon = {
  slug: string;
  name: string;
  category: string;
  address: string;
  city: string;
  plan: string;
  color: string;
  latitude: number | null;
  longitude: number | null;
  photo: string | null;
  cover: string | null;
  min_price: number | null;
  rating: number | null;
  reviews_count: number;
  bookings_count: number;
  distance_km: number | null;
};
export type Service = { id: number; name: string; price: number; duration: number };
export type Studio = Salon & {
  services: Service[];
  employees: { id: number; name: string }[];
  reviews: { rating: number; comment: string }[];
};
export function money(n: number | string) {
  return (
    new Intl.NumberFormat('sr-Latn-BA', { maximumFractionDigits: 2 }).format(Number(n)) + ' KM'
  );
}
export const categories = [
  'Berber',
  'Ženski frizer',
  'Frizer',
  'Fizioterapeut',
  'Pedikir',
  'Manikir',
  'Obrve',
  'Kiropraktičar',
];
export function photo(s: Salon, cover = false) {
  const fallback = [1, 2, 2, 4, 6, 3, 5, 4][categories.indexOf(s.category)] || 2;
  const path =
    (cover && s.plan !== 'basic' && s.cover) || s.photo || '/images/salon-' + fallback + '.jpg';
  const base = (import.meta.env.VITE_PUBLIC_SITE_URL || '').replace(/\/$/, '');
  return path.startsWith('/storage/') ? base + path : path;
}
export function today(offset = 0) {
  const date = new Date(Date.now() + offset * 86400000);
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Sarajevo',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(date);
  return ['year', 'month', 'day'].map((k) => parts.find((p) => p.type === k)?.value).join('-');
}
