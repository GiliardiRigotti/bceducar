<template><div ref="content" style="display:none"><slot></slot></div></template>
<script setup lang="ts">
import L from 'leaflet';
import { onMounted, onBeforeUnmount, ref, watch, shallowRef } from 'vue';
import { GeoMarkerData, LatLng } from '@/types';
const props = defineProps<{ map: L.Map; marker: GeoMarkerData; draggable?: boolean }>();
const emit = defineEmits<{ (action: 'new-position', payload: LatLng): void; (action: 'click', payload: GeoMarkerData): void }>();
const content = ref<HTMLElement>();
const internalMarker = shallowRef<L.Marker>();
function position() { return props.marker.position as unknown as { lat: number; lng: number }; }
function report(point: L.LatLng) { emit('new-position', {lat: point.lat, lng: point.lng}); }
const mapClick = (event: L.LeafletMouseEvent) => { if (props.draggable) { internalMarker.value?.setLatLng(event.latlng); report(event.latlng); } };
onMounted(() => {
  const point = position();
  if (!point || !Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return;
  const html = props.marker.color === 'blue'
    ? '<svg width="28" height="32" viewBox="0 0 28 32" aria-hidden="true"><path fill="#1769d2" stroke="white" stroke-width="2" d="M14 1C7 1 2 6 2 13c0 8 12 18 12 18s12-10 12-18C26 6 21 1 14 1Z"/><circle cx="14" cy="12" r="4" fill="white"/></svg>'
    : '<span aria-hidden="true">&#128205;</span>';
  const icon = L.divIcon({ className: 'bc-map-pin', html, iconSize: [28, 32], iconAnchor: [14, 32] });
  internalMarker.value = L.marker([point.lat, point.lng], { icon, draggable: !!props.draggable, title: props.marker.title || '' }).addTo(props.map);
  if (content.value?.childNodes.length) { const popup = content.value.cloneNode(true) as HTMLElement; popup.style.display = 'block'; internalMarker.value.bindPopup(popup); }
  if (props.marker.permanentLabel) {
    const label = document.createElement('span');
    label.textContent = props.marker.permanentLabel;
    internalMarker.value.bindTooltip(label, {permanent: true, direction: 'top', offset: [0, -30]});
  }
  internalMarker.value.on('click', () => emit('click', props.marker));
  internalMarker.value.on('dragend', () => report(internalMarker.value!.getLatLng()));
  props.map.on('click', mapClick);
});
watch(() => props.draggable, value => { if (value) internalMarker.value?.dragging?.enable(); else internalMarker.value?.dragging?.disable(); });
watch(() => props.marker.position, () => { const point = position(); if (point && Number.isFinite(point.lat) && Number.isFinite(point.lng)) internalMarker.value?.setLatLng([point.lat, point.lng]); }, {deep:true});
onBeforeUnmount(() => { props.map.off('click', mapClick); internalMarker.value?.remove(); });
defineExpose({ internalMarker });
</script>
<style>.bc-map-pin {font-size:26px;}</style>
