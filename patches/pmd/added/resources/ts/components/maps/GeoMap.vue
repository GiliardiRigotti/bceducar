<template>
  <div class="google-map">
    <p v-if="unavailable" role="status" class="alert alert-warning">Mapa temporariamente indisponível. Utilize a lista de escolas para continuar.</p>
    <div ref="container" class="google-map-container"></div>
    <slot v-if="mapInstance" :map="mapInstance"></slot>
  </div>
</template>
<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onMounted, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
const props = withDefaults(defineProps<{ config?: Record<string, unknown>; lat: number; lng: number; zoom: number }>(), { zoom: 13 });
const container = ref<HTMLElement>();
const mapInstance = shallowRef<L.Map>();
const unavailable = ref(false);
onMounted(() => {
  try {
    const config = (window.config as any).tiles;
    mapInstance.value = L.map(container.value!).setView([props.lat, props.lng], props.zoom);
    L.tileLayer(config.tileUrl, { attribution: config.attribution, maxZoom: config.maxZoom })
      .on('tileerror', () => { unavailable.value = true; }).addTo(mapInstance.value);
  } catch { unavailable.value = true; }
});
watch(() => [props.lat, props.lng], () => { mapInstance.value?.panTo([props.lat, props.lng]); });
onBeforeUnmount(() => { mapInstance.value?.remove(); });
</script>
<style scoped>
.google-map { position: relative; min-height: 300px; }
.google-map-container { height: 100%; min-height: 300px; width: 100%; z-index: 0; }
</style>
