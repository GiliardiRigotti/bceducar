import { it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import '@/plugin/sortby';
import StudentFields from '@/modules/preregistration/components/StudentFields.vue';
vi.mock('@/store/general', () => ({ useGeneralStore: () => ({
  map: {center: {lat: -26.99, lng: -48.63}, zoom: 13, config: {}},
}) }));
function mountSelection(address = {} as any, renewal = false) {
  const student = {grade: '1', period: '1', school: null, secondSchool: null,
    secondPeriod: '1', useSecondSchool: true, waitingList: []} as any;
  if (renewal) student.match = {registration: {school: {id: '1'}, grade: {id: '1'}}};
  return shallowMount(StudentFields, {props: {
    student, responsible: {address, useSecondAddress: false} as any,
    stage: {type: 'REGISTRATION', allowWaitingList: true, radius: 100,
      renewalAtSameSchool: renewal, process: {waitingListLimit: 10,
        grades: [{id: '1', name: 'First'}], periods: [{id: '1', name: 'Day'}],
        schools: [{id: '1', name: 'Near', latitude: -26.99, longitude: -48.63},
          {id: '2', name: 'Far', latitude: -27.2, longitude: -48.8}],
        vacancies: [{school: '1', grade: '1', period: '1', available: 2},
          {school: '2', grade: '1', period: '1', available: 2}],
      }} as any,
  }, global: {provide: {$filters: {}}, stubs: {XBtn: {props: ['label'], template: '<button>{{ label }}</button>'}}}});
}
it('includes all eligible school units even beyond the previous distance radius', () => {
  const wrapper = mountSelection({lat: -26.99, lng: -48.63});
  expect((wrapper.vm as any).markerClosestSchools.map((school: any) => school.id)).toEqual(['1', '2']);
  expect(wrapper.text()).not.toContain('3ª opção');
  (wrapper.vm as any).addWaitingList();
  expect((wrapper.vm as any).modelStudent.waitingList).toHaveLength(0);
  wrapper.unmount();
});
it('offers schools without residence coordinates and does not render a residence pin', () => {
  const wrapper = mountSelection();
  expect((wrapper.vm as any).markerClosestSchools).toHaveLength(2);
  expect((wrapper.vm as any).hasAddressCoordinates).toBe(false);
  wrapper.unmount();
});
it('preserves renewal restricted to the previous school', () => {
  const wrapper = mountSelection({}, true);
  expect((wrapper.vm as any).closestSchools.map((school: any) => school.id)).toEqual(['1']);
  wrapper.unmount();
});

it('orders schools from the declared address without selecting one automatically', () => {
  const wrapper = mountSelection({lat: -27.2, lng: -48.8});
  expect((wrapper.vm as any).closestSchools.map((school: any) => school.id)).toEqual(['2', '1']);
  expect((wrapper.vm as any).nearestSchool).toContain('Far');
  expect((wrapper.vm as any).nearestSchool).toContain('linha reta');
  expect((wrapper.vm as any).modelStudent.school).toBeNull();
  expect((wrapper.vm as any).homePositions).toEqual([{lat: -27.2, lng: -48.8}]);
  wrapper.unmount();
});
