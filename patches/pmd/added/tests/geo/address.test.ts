import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import AddressFields from '@/components/form/AddressFields.vue';
vi.mock('@/store/general', () => ({ useGeneralStore: () => ({entity: {ibgeCodes: []}, map: {center: {lat: -26.99, lng: -48.63}}}) }));
vi.mock('@/components/x-form/XField.vue', () => ({default: {template: '<input />'}}));
describe('registration uses the declared address', () => {
  it('does not ask for geolocation or confirmation and does not invent coordinates', () => {
    const getCurrentPosition = vi.fn();
    Object.defineProperty(navigator, 'geolocation', {configurable: true, value: {getCurrentPosition}});
    const data = {postalCode: '88330-000', address: 'Rua Teste', number: '10', neighborhood: 'Centro', city: 'Balneario Camboriu', stateAbbreviation: 'SC'} as any;
    const wrapper = shallowMount(AddressFields, {props: {data, name: 'address', setFieldValue: vi.fn(), errors: {}, fetchingAddressLatLng: false}});
    expect(wrapper.findAll('button')).toHaveLength(0);
    expect(wrapper.text()).not.toContain('Confirmar localização');
    expect(getCurrentPosition).not.toHaveBeenCalled();
    expect(data.lat).toBeUndefined();
    expect(data.lng).toBeUndefined();
    wrapper.unmount();
  });
});
