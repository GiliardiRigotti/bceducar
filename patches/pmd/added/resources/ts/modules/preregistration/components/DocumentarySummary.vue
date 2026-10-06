<template>
  <section v-if="summary?.released && !intake" class="card p-3 my-3" aria-label="Situação documental">
    <h2 class="h5">Documentação</h2>
    <p class="font-weight-bold mb-2">{{ summary.label }}</p>
    <template v-if="summary.released">
      <p class="mb-1">Modalidade: {{ summary.mode || 'Aguardando escolha do responsável' }}</p>
      <p v-if="summary.deadline" class="mb-1">Prazo de entrega: {{ formatDeadline(summary.deadline) }}</p>
      <p class="mb-1">Obrigatórios recebidos: {{ summary.received }} de {{ summary.required }} · Aprovados: {{ summary.approved }} de {{ summary.required }}</p>
      <p v-if="summary.corrections" class="text-danger mb-2">Documentos com correção: {{ summary.corrections }}</p>
      <p v-if="!summary.enrolled" class="mb-2">Após a conferência dos documentos, a escola poderá aprovar a documentação e efetivar a matrícula no i-Educar.</p>
      <a v-if="summary.triageUrl" :href="summary.triageUrl" class="btn btn-outline-primary align-self-start">{{ summary.enrolled ? 'Consultar matrícula efetivada' : 'Abrir triagem documental' }}</a>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { DocumentarySummary } from '../types';

defineProps<{ summary?: DocumentarySummary | null; intake?: boolean }>();
const formatDeadline = (value: string) => new Date(value.replace(' ', 'T')).toLocaleString('pt-BR');
</script>
