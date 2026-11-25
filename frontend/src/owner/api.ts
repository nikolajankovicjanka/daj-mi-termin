export type Plan = 'basic' | 'business' | 'premium';
export type Hours = Record<string, string[]>;
export interface Salon {
  id: number;
  name: string;
  slug: string;
  address: string;
  city: string;
  latitude: number | string | null;
  longitude: number | string | null;
  category: string;
  plan: Plan;
  official_email: string;
  billing_email: string;
  color: string;
  photo: string | null;
  cover: string | null;
  expires_at: string | null;
  free_until: string | null;
}
export interface Service {
  id: number;
  name: string;
  price: number | string;
  duration: number;
}
export interface Employee {
  id: number;
  name: string;
  working_hours: Hours;
}
export interface Workspace {
  salon: Salon;
  email: string;
  employees: Employee[];
  services: Service[];
}
export interface Booking {
  id: number;
  employee_id: number;
  service_name: string;
  customer_name: string;
  customer_email: string | null;
  start_at: string;
  end_at: string;
  status: string;
  source: string;
}
export interface Slot {
  employee_id: number;
  employee_name: string;
  start_at: string;
  label: string;
}
export interface Thread {
  id: number;
  customer_name: string;
}
export interface Message {
  id: number;
  body: string;
  sender: string;
  created_at: string;
}
export const planNames: Record<Plan, string> = {
  basic: 'Osnovni',
  business: 'Business',
  premium: 'Premium',
};
export const days = ['Ponedjeljak', 'Utorak', 'Srijeda', 'Četvrtak', 'Petak', 'Subota', 'Nedjelja'];
export const dayKey = (index: number) => String(index + 1);
export function localDate(date = new Date()) {
  return new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Europe/Sarajevo',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date);
}
export function time(value: string) {
  return new Intl.DateTimeFormat('bs-BA', {
    timeZone: 'Europe/Sarajevo',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(value));
}
export function errorText(e: unknown) {
  return e instanceof Error ? e.message : 'Zahtjev nije uspio.';
}
export async function api<T>(path: string, method = 'GET', body?: unknown): Promise<T> {
  const form = body instanceof FormData;
  const csrf = document.cookie
    .split('; ')
    .find((c) => c.startsWith('XSRF-TOKEN='))
    ?.slice(11);
  const response = await fetch('/api/owner/' + path, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {}),
      ...(!form && body !== undefined ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body === undefined ? undefined : form ? body : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const first = Object.values(data.errors || {}).flat()[0];
    throw new Error(
      typeof first === 'string'
        ? first
        : data.message || 'Zahtjev nije uspio. Osvježite stranicu i pokušajte ponovo.',
    );
  }
  return data as T;
}
