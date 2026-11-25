import { Capacitor } from '@capacitor/core';
import { Geolocation } from '@capacitor/geolocation';

export async function getLocation(): Promise<{ latitude: number; longitude: number }> {
  const options = { enableHighAccuracy: false, maximumAge: 120000, timeout: 12000 };
  if (Capacitor.isNativePlatform()) {
    const permission = await Geolocation.requestPermissions({
      permissions: ['coarseLocation', 'location'],
    });
    if (permission.location !== 'granted' && permission.coarseLocation !== 'granted') {
      throw new Error('Pristup lokaciji nije odobren.');
    }
    return (await Geolocation.getCurrentPosition(options)).coords;
  }
  if (!navigator.geolocation) throw new Error('Uređaj ne podržava određivanje lokacije.');
  return new Promise((resolve, reject) =>
    navigator.geolocation.getCurrentPosition(
      (position) => resolve(position.coords),
      reject,
      options,
    ),
  );
}
