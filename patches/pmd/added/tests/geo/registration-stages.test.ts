import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import RegistrationStages from '../../resources/ts/modules/protocol/components/RegistrationStages.vue';

describe('separate business stages', () => {
  it('blocks documentary progress while intake is under review', () => {
    const view = mount(RegistrationStages, { props: { status: 'WAITING' } });
    expect(view.text()).toContain('Pré-matrícula em análise');
    expect(view.findAll('li').map(el => el.attributes('data-state'))).toEqual(['active', 'waiting', 'waiting']);
  });
  it('deferment opens documentation without showing enrollment', () => {
    const view = mount(RegistrationStages, { props: { status: 'SUMMONED' } });
    expect(view.text()).toContain('Pré-matrícula deferida');
    expect(view.text()).not.toContain('Matrícula efetivada');
    expect(view.findAll('li').map(el => el.attributes('data-state'))).toEqual(['complete', 'active', 'waiting']);
  });
  it('document approval waits for explicit enrollment', () => {
    const view = mount(RegistrationStages, { props: { status: 'IN_CONFIRMATION', documentationStatus: 'APPROVED' } });
    expect(view.text()).toContain('Aguardando efetivação pela unidade escolar');
    expect(view.findAll('li').map(el => el.attributes('data-state'))).toEqual(['complete', 'complete', 'active']);
  });
  it('enrollment completes all three stages', () => {
    const view = mount(RegistrationStages, { props: { status: 'ACCEPTED', documentationStatus: 'APPROVED' } });
    expect(view.text()).toContain('Matrícula efetivada');
    expect(view.findAll('li').map(el => el.attributes('data-state'))).toEqual(['complete', 'complete', 'complete']);
  });
});
