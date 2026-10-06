<template>
  <ol class="registration-stages" aria-label="Pré-matrícula → Documentação → Matrícula">
    <li :data-state="released ? 'complete' : 'active'"><strong>1. Pré-matrícula</strong><span>{{ released ? 'Pré-matrícula deferida' : (status === 'REJECTED' ? 'Pré-matrícula indeferida' : 'Pré-matrícula em análise') }}</span></li>
    <li :data-state="approved ? 'complete' : (released ? 'active' : 'waiting')"><strong>2. Documentação</strong><span>{{ approved ? 'Documentação aprovada' : (released ? 'Entrega e análise documental' : 'Disponível após o deferimento') }}</span></li>
    <li :data-state="enrolled ? 'complete' : (approved ? 'active' : 'waiting')"><strong>3. Matrícula</strong><span>{{ enrolled ? 'Matrícula efetivada' : (approved ? 'Aguardando efetivação pela unidade escolar' : 'Aguardando etapas anteriores') }}</span></li>
  </ol>
</template>
<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ status: string; documentationStatus?: string | null }>();
const enrolled = computed(() => props.status === 'ACCEPTED');
const released = computed(() => ['SUMMONED', 'IN_CONFIRMATION', 'ACCEPTED'].includes(props.status));
const approved = computed(() => released.value && (props.documentationStatus === 'APPROVED' || enrolled.value));
</script>
<style scoped>
.registration-stages{list-style:none;display:flex;flex-wrap:wrap;gap:12px;padding:0}
.registration-stages li{flex:1;min-width:160px;border:1px solid #ccd5df;border-radius:8px;padding:12px}
.registration-stages span{display:block;font-size:14px}
.registration-stages [data-state=active]{border:2px solid #175e95}
.registration-stages [data-state=complete]{background:#e6f4eb}
</style>
