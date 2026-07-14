(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('booking-form');
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('.booking-step'))
      .sort(function (a, b) { return parseInt(a.dataset.step, 10) - parseInt(b.dataset.step, 10); });

    var indicators = document.querySelectorAll('#booking-progress .step-indicator');
    var labels      = document.querySelectorAll('#booking-progress .step-label');
    var connectors   = document.querySelectorAll('#booking-progress .step-connector');
    var backBtn      = document.getElementById('booking-back');
    var nextBtn      = document.getElementById('booking-next');
    var btnLabel     = nextBtn.querySelector('.ob-btn-label');
    var btnArrow     = nextBtn.querySelector('.ob-btn-arrow');
    var btnSpinner   = nextBtn.querySelector('.ob-btn-spinner');
    var errorBox     = document.getElementById('ob-form-error');
    var overlay      = document.getElementById('ob-success-overlay');
    var successClose = document.getElementById('ob-success-close');
    var successRef    = overlay.querySelector('.ob-success-ref');

    var current = 0;
    var lastStep = steps.length - 1;
    var autoCloseTimer = null;

    /* ---------------------------------------------------------------
     * Journey map: address autocomplete (Nominatim) + route (OSRM),
     * drawn as a red line between the pickup and destination markers.
     * Both markers use a proper location-pin SVG icon, red fill.
     * ------------------------------------------------------------- */
    var JOURNEY_STEP_INDEX = 1;
    var map, pickupMarker, destMarker, routeLine;
    var pickupPoint = null, destPoint = null;
    var mapInitialised = false;

    function pinIcon() {
      var svg =
        '<svg class="ob-map-pin" width="34" height="44" viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg">' +
          '<path d="M17 1C8.7 1 2 7.7 2 16c0 11 15 27 15 27s15-16 15-27C32 7.7 25.3 1 17 1z" fill="#e03131" stroke="#0b0b0d" stroke-width="1.5"/>' +
          '<circle cx="17" cy="16" r="6.5" fill="#fff"/>' +
        '</svg>';
      return L.divIcon({
        className: '',
        html: svg,
        iconSize: [34, 44],
        iconAnchor: [17, 44],
        popupAnchor: [0, -40]
      });
    }

    function initMap() {
      if (mapInitialised || typeof L === 'undefined') return;
      var mapEl = document.getElementById('ob-map');
      if (!mapEl) return;
      mapInitialised = true;

      map = L.map(mapEl, { attributionControl: true, zoomControl: true }).setView([51.509, -0.118], 6);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
    }

    function setPoint(type, lat, lng, label) {
      initMap();
      var latlng = [parseFloat(lat), parseFloat(lng)];

      if (type === 'pickup') {
        pickupPoint = latlng;
        if (pickupMarker) map.removeLayer(pickupMarker);
        pickupMarker = L.marker(latlng, { icon: pinIcon() }).addTo(map).bindPopup('Pickup: ' + label);
      } else {
        destPoint = latlng;
        if (destMarker) map.removeLayer(destMarker);
        destMarker = L.marker(latlng, { icon: pinIcon() }).addTo(map).bindPopup('Drop-off: ' + label);
      }

      if (pickupPoint && destPoint) {
        drawRoute();
      } else {
        map.setView(latlng, 12);
      }
    }

    function drawRoute() {
      if (!pickupPoint || !destPoint) return;
      var url = 'https://router.project-osrm.org/route/v1/driving/' +
        pickupPoint[1] + ',' + pickupPoint[0] + ';' + destPoint[1] + ',' + destPoint[0] +
        '?overview=full&geometries=geojson';

      fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (routeLine) { map.removeLayer(routeLine); routeLine = null; }

          var bounds;
          if (json.routes && json.routes[0]) {
            var route = json.routes[0];
            var coords = route.geometry.coordinates.map(function (c) { return [c[1], c[0]]; });
            routeLine = L.polyline(coords, { color: '#e03131', weight: 4, opacity: 0.9 }).addTo(map);
            bounds = routeLine.getBounds();
            updateSummary(route.distance, route.duration);
          } else {
            routeLine = L.polyline([pickupPoint, destPoint], { color: '#e03131', weight: 4, opacity: 0.9, dashArray: '6 6' }).addTo(map);
            bounds = routeLine.getBounds();
          }
          map.fitBounds(bounds, { padding: [30, 30] });
        })
        .catch(function () {
          if (routeLine) { map.removeLayer(routeLine); }
          routeLine = L.polyline([pickupPoint, destPoint], { color: '#e03131', weight: 4, opacity: 0.9, dashArray: '6 6' }).addTo(map);
          map.fitBounds(routeLine.getBounds(), { padding: [30, 30] });
        });
    }

    function updateSummary(distanceMeters, durationSeconds) {
      var summary = document.getElementById('ob-map-summary');
      var distEl = document.getElementById('ob-distance-value');
      var durEl = document.getElementById('ob-duration-value');
      if (!summary) return;
      var miles = (distanceMeters / 1609.34).toFixed(1);
      var mins = Math.round(durationSeconds / 60);
      var hrs = Math.floor(mins / 60);
      var remMins = mins % 60;
      distEl.textContent = miles + ' mi';
      durEl.textContent = hrs > 0 ? (hrs + 'h ' + remMins + 'm') : (mins + ' min');
      summary.classList.remove('hidden');
    }

    function debounce(fn, delay) {
      var timer;
      return function () {
        var args = arguments, ctx = this;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
      };
    }

    function setupAutocomplete(inputId, listId, latId, lngId, pointType) {
      var input = document.getElementById(inputId);
      var list  = document.getElementById(listId);
      var latInput = document.getElementById(latId);
      var lngInput = document.getElementById(lngId);
      if (!input || !list) return;

      var currentResults = [];

      function hideList() {
        list.classList.add('hidden');
        list.innerHTML = '';
      }

      function renderResults(results) {
        currentResults = results;
        list.innerHTML = '';
        if (!results.length) { hideList(); return; }
        results.forEach(function (r) {
          var li = document.createElement('li');
          li.textContent = r.display_name;
          li.addEventListener('click', function () {
            input.value = r.display_name;
            latInput.value = r.lat;
            lngInput.value = r.lon;
            hideList();
            setPoint(pointType, r.lat, r.lon, r.display_name);
          });
          list.appendChild(li);
        });
        list.classList.remove('hidden');
      }

      var search = debounce(function (query) {
        if (!query || query.length < 3) { hideList(); return; }
        var url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=0&limit=5&q=' + encodeURIComponent(query);
        fetch(url, { headers: { 'Accept': 'application/json' } })
          .then(function (res) { return res.json(); })
          .then(renderResults)
          .catch(function () { hideList(); });
      }, 400);

      input.addEventListener('input', function () {
        latInput.value = '';
        lngInput.value = '';
        search(input.value.trim());
      });
      input.addEventListener('focus', function () {
        if (currentResults.length) list.classList.remove('hidden');
      });
      document.addEventListener('click', function (e) {
        if (e.target !== input && !list.contains(e.target)) hideList();
      });
    }

    setupAutocomplete('ob-pickup-address', 'ob-pickup-suggestions', 'ob-pickup-lat', 'ob-pickup-lng', 'pickup');
    setupAutocomplete('ob-destination-address', 'ob-destination-suggestions', 'ob-destination-lat', 'ob-destination-lng', 'destination');

    function showError(msg) {
      errorBox.textContent = msg;
      errorBox.classList.remove('hidden');
    }
    function clearError() {
      errorBox.classList.add('hidden');
      errorBox.textContent = '';
    }

    function validateStep(index) {
      var step = steps[index];
      var fields = step.querySelectorAll('input, select, textarea');
      for (var i = 0; i < fields.length; i++) {
        var f = fields[i];
        if (!f.checkValidity()) {
          f.reportValidity();
          return false;
        }
      }
      return true;
    }

    function renderStep() {
      steps.forEach(function (step, i) {
        var isActive = i === current;
        step.classList.toggle('active', isActive);
        step.style.display = isActive ? 'grid' : 'none';
      });
      indicators.forEach(function (el, i) {
        el.classList.toggle('border-gold', i <= current);
        el.classList.toggle('bg-gold', i === current);
        el.classList.toggle('text-ink', i === current);
        el.classList.toggle('text-muted-foreground', i !== current);
        el.classList.toggle('border-white/20', i > current);
      });
      labels.forEach(function (el, i) {
        el.classList.toggle('text-gold', i <= current);
        el.classList.toggle('text-muted-foreground', i > current);
      });
      connectors.forEach(function (el, i) {
        el.classList.toggle('bg-gold', i < current);
        el.classList.toggle('bg-white/10', i >= current);
      });
      backBtn.disabled = current === 0;
      btnLabel.textContent = current === lastStep ? 'Confirm Booking' : 'Continue';
      clearError();

      if (current === JOURNEY_STEP_INDEX) {
        initMap();
        setTimeout(function () {
          if (map) {
            map.invalidateSize();
            if (pickupPoint && destPoint) {
              map.fitBounds((routeLine || L.polyline([pickupPoint, destPoint])).getBounds(), { padding: [30, 30] });
            }
          }
        }, 60);
      }
    }

    backBtn.addEventListener('click', function () {
      if (current > 0) {
        current--;
        renderStep();
      }
    });

    function setLoading(isLoading) {
      nextBtn.disabled = isLoading;
      backBtn.disabled = isLoading || current === 0;
      btnArrow.classList.toggle('hidden', isLoading);
      btnSpinner.classList.toggle('hidden', !isLoading);
    }

    function submitBooking() {
      clearError();
      setLoading(true);

      var formData = new FormData(form);
      formData.append('action', 'oracle_booking_submit');
      formData.append('nonce', oracleBooking.nonce);

      fetch(oracleBooking.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          setLoading(false);
          if (json.success) {
            showSuccess(json.data.reference);
            form.reset();
            current = 0;
            renderStep();
          } else {
            showError((json.data && json.data.message) || 'Something went wrong. Please try again.');
          }
        })
        .catch(function () {
          setLoading(false);
          showError('Network error. Please check your connection and try again.');
        });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!validateStep(current)) return;

      if (current < lastStep) {
        current++;
        renderStep();
      } else {
        submitBooking();
      }
    });

    function showSuccess(reference) {
      successRef.textContent = reference ? 'Reference: ' + reference : '';
      overlay.classList.remove('hidden');
      requestAnimationFrame(function () { overlay.classList.add('ob-show'); });

      clearTimeout(autoCloseTimer);
      autoCloseTimer = setTimeout(hideSuccess, 3000);
    }

    function hideSuccess() {
      clearTimeout(autoCloseTimer);
      overlay.classList.remove('ob-show');
      setTimeout(function () { overlay.classList.add('hidden'); }, 250);
    }

    successClose.addEventListener('click', hideSuccess);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) hideSuccess();
    });

    renderStep();
  });
})();
