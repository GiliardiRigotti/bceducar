// Keep the legacy form entry points and latitude/longitude fields.
var IeducarSingletonMap = function () {
  this.map = null; this.marker = null;
  this.lat = $j('#latitude'); this.lng = $j('#longitude');
};
IeducarSingletonMap.prototype.render = function () {
  this.lat.add(this.lng).prop('readonly', false).css('background-color', '');
  var that = this;
  Promise.all([window.bcLeafletReady, fetch('/geo/config').then(function(r) { return r.json(); })]).then(function(values) {
    if (that.map) return;
    var L = values[0], config = values[1];
    that.map = L.map('map').setView([config.defaultLatitude, config.defaultLongitude], config.defaultZoom);
    L.tileLayer(config.tileUrl, {attribution: config.attribution, maxZoom: config.maxZoom}).addTo(that.map);
    function place(lat, lng, save) {
      if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat)>90 || Math.abs(lng)>180) return;
      if (that.marker) that.marker.setLatLng([lat,lng]);
      else {
        that.marker = L.marker([lat,lng], {draggable:true}).addTo(that.map);
        that.marker.on('dragend', function() { var p=that.marker.getLatLng(); that.lat.val(p.lat); that.lng.val(p.lng); });
      }
      if (save) { that.lat.val(lat); that.lng.val(lng); }
    }
    if (that.lat.val().trim() && that.lng.val().trim()) {
      var lat=Number(that.lat.val()), lng=Number(that.lng.val()); place(lat,lng,false); that.map.panTo([lat,lng]);
    }
    that.map.on('click', function(e) { place(e.latlng.lat,e.latlng.lng,true); });
    that.lat.add(that.lng).on('change.bc-map', function() { if (that.lat.val().trim() && that.lng.val().trim()) place(Number(that.lat.val()),Number(that.lng.val()),false); });
  }).catch(function() { $j('#map').text('Mapa indisponível. O cadastro pode continuar com os campos latitude e longitude.'); });
};
IeducarSingletonMap.prototype.reload = function () { if (this.map) this.map.invalidateSize(); else this.render(); };
