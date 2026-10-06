import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DocumentarySummary from '../../resources/ts/modules/preregistration/components/DocumentarySummary.vue';

const summary = {
  released: true, label: 'Aguardando documentos', mode: null, deadline: null,
  required: 3, received: 1, approved: 0, corrections: 1, enrolled: false,
  triageUrl: '/bc/matriculas/42',
};

describe('native school documentary summary', () => {
  it('preserves legacy screens without a documentary link', () => {
    expect(mount(DocumentarySummary, { props: { summary: null } }).find('section').exists()).toBe(false);
  });
  it('shows guardian choice, real progress and authorized triage navigation', () => {
    const view = mount(DocumentarySummary, { props: { summary } });
    expect(view.text()).toContain('Aguardando escolha do responsável');
    expect(view.text()).toContain('1 de 3');
    expect(view.text()).toContain('Documentos com correção: 1');
    expect(view.find('a').attributes('href')).toBe('/bc/matriculas/42');
  });
  it('formats the native GraphQL deadline as a local date', () => {
    const view = mount(DocumentarySummary, { props: { summary: { ...summary, deadline: '2026-10-05 12:00:00' } } });
    expect(view.text()).toContain('05/10/2026');
    expect(view.text()).not.toContain('Invalid Date');
  });
  it('does not offer documentary navigation before deferment', () => {
    const view = mount(DocumentarySummary, { props: { summary: { ...summary, released: false, triageUrl: null } } });
    expect(view.find('section').exists()).toBe(false);
    expect(view.find('a').exists()).toBe(false);
    expect(view.text()).not.toContain('Obrigatórios recebidos');
  });
  it('hides documentary progress during initial intake even with a stale released summary', () => {
    const view = mount(DocumentarySummary, { props: { summary, intake: true } });
    expect(view.find('section').exists()).toBe(false);
    expect(view.text()).not.toContain('Obrigatórios recebidos');
  });
  it('distinguishes documentary approval from effective enrollment', () => {
    const view = mount(DocumentarySummary, { props: { summary: { ...summary, label: 'Documentação aprovada — aguardando efetivação' } } });
    expect(view.text()).toContain('efetivar a matrícula no i-Educar');
    expect(view.text()).not.toContain('Consultar matrícula efetivada');
    const enrolled = mount(DocumentarySummary, { props: { summary: { ...summary, enrolled: true, label: 'Matrícula efetivada' } } });
    expect(enrolled.text()).toContain('Consultar matrícula efetivada');
    expect(enrolled.text()).not.toContain('A matrícula depende');
  });
});
