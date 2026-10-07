<template>
  <geo-marker-component v-for="marker in markers" :key="marker.id" :map="map" :marker="marker" @click="$emit('click', $event)">
    <slot :map="map" :marker="marker"></slot>
  </geo-marker-component>
</template>
<script setup lang="ts">
import type { Map } from 'leaflet';
import { watch, nextTick } from 'vue';
import { GeoMarkerData } from '@/types';
import GeoMarkerComponent from './GeoMarker.vue';
const props = defineProps<{map: Map; markers: GeoMarkerData[]; fitBounds?: boolean; extraPositions?: {lat: number; lng: number}[]}>();
watch(() => [props.markers, props.extraPositions], async () => {
  if (!props.fitBounds) return;
  const points = [...props.markers.map(marker => marker.position), ...(props.extraPositions || [])]
    .filter(point => point && Number.isFinite(point.lat) && Number.isFinite(point.lng) && Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180)
    .map(point => [point!.lat, point!.lng] as [number, number]);
  if (!points.length) return;
  await nextTick();
  props.map.fitBounds(points, {padding: [35, 45], maxZoom: 15});
}, {immediate: true, deep: true});
defineEmits(['click']);
</script>
