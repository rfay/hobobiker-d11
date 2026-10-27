/**
 * @file
 * Draws each .tripinfo-map: the KML or KMZ file named in its data-kml attribute on an OpenStreetMap base map.
 * The Drupal 6 tripinfo_filtermap.js, as a behavior.
 */
((Drupal, once) => {
  // A KMZ file is a zip archive holding one KML document (doc.kml).
  const kmzToText = (buffer) =>
    JSZip.loadAsync(buffer).then((zip) => {
      const kml = zip.file(/\.kml$/i)[0];
      if (!kml) {
        throw new Error('no .kml file inside the KMZ');
      }
      return kml.async('string');
    });

  const showMap = (element) => {
    const kmlUrl = element.getAttribute('data-kml');
    const map = L.map(element, { scrollWheelZoom: false }).setView([20, -90], 3);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);
    L.control.scale().addTo(map);

    const isKmz = /\.kmz$/i.test(kmlUrl.split('?')[0]);
    fetch(kmlUrl)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return isKmz ? response.arrayBuffer().then(kmzToText) : response.text();
      })
      .then((text) => {
        const kml = new DOMParser().parseFromString(text, 'text/xml');
        const layer = L.geoJSON(toGeoJSON.kml(kml), {
          style: (feature) => ({
            color: feature.properties.stroke || '#d00',
            weight: feature.properties['stroke-width'] || 3,
            opacity: feature.properties['stroke-opacity'] || 0.9,
          }),
          onEachFeature: (feature, featureLayer) => {
            if (feature.properties.name) {
              const popup = document.createElement('span');
              popup.textContent = feature.properties.name;
              featureLayer.bindPopup(popup);
            }
          },
        }).addTo(map);
        if (layer.getBounds().isValid()) {
          map.fitBounds(layer.getBounds());
        }
      })
      .catch((error) => {
        window.console?.error(`tripinfo map: could not load ${kmlUrl}: ${error.message}`);
      });
  };

  Drupal.behaviors.hobobikerTripinfoMap = {
    attach(context) {
      once('tripinfo-map', '.tripinfo-map', context).forEach(showMap);
    },
  };
})(Drupal, once);
