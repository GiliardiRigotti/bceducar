// Legacy loader retained at the existing URL; maps now use locally hosted Leaflet.
window.bcLeafletReady = window.bcLeafletReady || new Promise(function(resolve, reject) {
  if (window.L) { resolve(window.L); return; }
  var css = document.createElement('link'); css.rel = 'stylesheet'; css.href = '/vendor/maps/leaflet.css'; document.head.appendChild(css);
  var script = document.createElement('script'); script.src = '/vendor/maps/leaflet.js';
  script.onload = function() { resolve(window.L); }; script.onerror = reject; document.head.appendChild(script);
});
