import { it, expect, vi } from 'vitest';
vi.mock('@/store/general', () => ({useGeneralStore: () => ({})}));
import { parseResponsibleFieldsFromProcess } from '@/util';
it('adds a required email field when the process has none configured', () => {
  const result = parseResponsibleFieldsFromProcess({fields: []} as any);
  expect(result.fields.filter(field => field.key === 'responsible_email')).toHaveLength(1);
  expect(result.fields[0].rules).toEqual({required: true, email: true});
  expect(result.data.responsible_email).toBeNull();
});
it('keeps one email field when the process already provides it', () => {
  const result = parseResponsibleFieldsFromProcess({fields: [{order: 1, required: false,
    field: {id: '1', internal: 'responsible_email', name: 'Email', group: 'RESPONSIBLE', type: 'EMAIL'}}]} as any);
  expect(result.fields.filter(field => field.key === 'responsible_email')).toHaveLength(1);
  expect(result.fields[0].rules).toEqual({required: true, email: true});
});
