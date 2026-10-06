import { it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';
import GeoMarkers from '@/components/maps/GeoMarkers.vue';
it('fits all available school locations and recalculates when the options change', async () => {
  const map = {fitBounds: vi.fn()} as any;
  const markers = [{id: 1, position: {lat: -26.99, lng: -48.63}},
    {id: 2, position: {lat: -27.1, lng: -48.7}}] as any;
  const wrapper = shallowMount(GeoMarkers, {props: {map, markers, fitBounds: true}});
  await nextTick();
  expect(map.fitBounds).toHaveBeenLastCalledWith([[-26.99, -48.63], [-27.1, -48.7]], {padding: [35, 45], maxZoom: 15});
  await wrapper.setProps({markers: [markers[0]]});
  await nextTick();
  expect(map.fitBounds).toHaveBeenLastCalledWith([[-26.99, -48.63]], {padding: [35, 45], maxZoom: 15});
  wrapper.unmount();
});
