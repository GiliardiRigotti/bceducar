import axios from 'axios';

export async function geocode(address: string) {
  const response = await axios.post('/geo/search', { address }, { timeout: 10000 });
  return response.data.result as { latitude: number; longitude: number; formattedAddress: string };
}
