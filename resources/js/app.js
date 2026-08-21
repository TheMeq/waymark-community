//
import Alpine from 'alpinejs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import siteBanner from './site-banner';
import photoUpload from './photo-upload';

window.Alpine = Alpine;
document.documentElement.classList.add('js');

Alpine.data('siteBanner', siteBanner);
Alpine.data('photoUpload', photoUpload);
Alpine.data('walkMap', (payload) => ({
    initialise() {
        const map = L.map(this.$refs.canvas, { scrollWheelZoom: false });

        L.tileLayer(payload.tile_url, {
            attribution: payload.attribution,
            maxZoom: 19,
        }).addTo(map);

        if (payload.route_points.length > 1) {
            L.polyline(payload.route_points, { color: '#315c45', weight: 4 }).addTo(map);
        }

        if (payload.meeting_point) {
            L.circleMarker(payload.meeting_point, {
                color: '#ffffff',
                fillColor: '#315c45',
                fillOpacity: 1,
                radius: 8,
                weight: 3,
            }).addTo(map).bindTooltip('Meeting point');
        }

        map.fitBounds(payload.bounds, { padding: [24, 24] });
    },
}));

Alpine.start();
