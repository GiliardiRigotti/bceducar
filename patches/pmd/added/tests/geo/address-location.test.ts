import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { defineComponent, reactive, toRef } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { useAddressLocation } from '@/composables/useAddressLocation';
import { geocode } from '@/services/geocoding';
vi.mock('@/services/geocoding', () => ({geocode: vi.fn()}));

function setup() {
  const state = reactive({address: {address: 'Rua Teste', number: '10', city: 'Balneário Camboriú', stateAbbreviation: 'SC', neighborhood: 'Centro'} as any});
  let location: ReturnType<typeof useAddressLocation>;
  const wrapper = mount(defineComponent({setup() {location = useAddressLocation(toRef(state, 'address')); return () => null;}}));
  return {state, wrapper, get location() {return location!;}};
}
describe('automatic address location', () => {
  beforeEach(() => {vi.useFakeTimers(); vi.resetAllMocks(); (window as any).config = {tiles: {geocodingEnabled: true}};});
  afterEach(() => {vi.useRealTimers();});
  it('debounces the declared address and stores valid coordinates', async () => {
    vi.mocked(geocode).mockResolvedValue({latitude: -26.99, longitude: -48.63, formattedAddress: 'Rua Teste'});
    const ctx = setup();
    expect(geocode).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1200); await flushPromises();
    expect(geocode).toHaveBeenCalledTimes(1);
    expect(ctx.state.address.lat).toBe(-26.99);
    expect(ctx.location.located.value).toBe(true);
    ctx.wrapper.unmount();
  });
  it('discards stale responses after the user changes the address', async () => {
    let finish: any;
    vi.mocked(geocode).mockImplementationOnce(() => new Promise(resolve => {finish = resolve;}));
    const ctx = setup();
    vi.advanceTimersByTime(1200);
    ctx.state.address.number = '20'; await flushPromises();
    finish({latitude: -26.99, longitude: -48.63}); await flushPromises();
    expect(ctx.state.address.lat).toBeUndefined();
    ctx.wrapper.unmount();
  });
  it('allows continuing when the service fails and never fabricates a pin', async () => {
    vi.mocked(geocode).mockRejectedValue(new Error('Unavailable'));
    const ctx = setup();
    vi.advanceTimersByTime(1200); await flushPromises();
    expect(ctx.location.message.value).toContain('continuar');
    expect(ctx.state.address.lat).toBeUndefined();
    ctx.wrapper.unmount();
  });
  it('does not query incomplete addresses or when the provider is disabled', async () => {
    (window as any).config.tiles.geocodingEnabled = false;
    const ctx = setup(); vi.advanceTimersByTime(2000);
    expect(geocode).not.toHaveBeenCalled();
    ctx.wrapper.unmount();
  });
  it('does not send a queued address after unmounting', async () => {
    const ctx = setup(); ctx.wrapper.unmount(); vi.advanceTimersByTime(2000);
    expect(geocode).not.toHaveBeenCalled();
  });
});
