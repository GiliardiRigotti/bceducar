import { computed, onBeforeUnmount, ref, watch, Ref } from 'vue';
import { Address } from '@/types';
import { geocode } from '@/services/geocoding';

export function useAddressLocation(address: Ref<Address>) {
  const status = ref('idle');
  let timer: ReturnType<typeof setTimeout> | undefined;
  let revision = 0;
  let disposed = false;
  const located = computed(() => Number.isFinite(address.value.lat) &&
    Number.isFinite(address.value.lng) && Math.abs(address.value.lat!) <= 90 &&
    Math.abs(address.value.lng!) <= 180 && !(address.value.lat === 0 && address.value.lng === 0));
  const message = computed(() => status.value === 'loading' ? 'Localizando o endereço…' :
    status.value === 'unavailable' ? 'Não foi possível localizar o endereço. Você pode continuar o cadastro.' :
    located.value ? 'Pin do endereço informado. A localização pode ser aproximada.' : '');
  watch(() => [address.value.postalCode, address.value.address, address.value.number,
    address.value.neighborhood, address.value.city, address.value.stateAbbreviation], (_fields, previous) => {
    const current = ++revision;
    clearTimeout(timer);
    if (previous) {
      address.value.lat = undefined;
      address.value.lng = undefined;
      address.value.manualChangeLocation = false;
    } else if (located.value) {
      status.value = 'located';
      return;
    }
    status.value = 'idle';
    if (!(window.config as any)?.tiles?.geocodingEnabled ||
      ![address.value.address, address.value.number, address.value.city, address.value.stateAbbreviation].every(v => v?.trim())) return;
    const query = [address.value.address, address.value.number, address.value.neighborhood,
      address.value.city, address.value.stateAbbreviation, 'Brasil'].filter(Boolean).join(', ');
    timer = setTimeout(async () => {
      status.value = 'loading';
      try {
        const result = await geocode(query);
        if (disposed || revision !== current) return;
        if (!result || !Number.isFinite(result.latitude) || !Number.isFinite(result.longitude) ||
          Math.abs(result.latitude) > 90 || Math.abs(result.longitude) > 180 ||
          (result.latitude === 0 && result.longitude === 0)) throw new Error('Invalid coordinates');
        address.value.lat = result.latitude;
        address.value.lng = result.longitude;
        address.value.manualChangeLocation = false;
        status.value = 'located';
      } catch {
        if (!disposed && revision === current) status.value = 'unavailable';
      }
    }, 1200);
  }, {immediate: true});
  onBeforeUnmount(() => { disposed = true; revision++; clearTimeout(timer); });
  return {located, status, message};
}
