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
Alpine.data('galleryLightbox', () => ({
    content: '',
    trigger: null,
    links: [],
    index: 0,
    touchX: null,
    initialise() {
        this.links = [...this.$el.querySelectorAll('[data-gallery-photo]')];
    },
    async open(link) {
        this.trigger = link;
        this.index = this.links.indexOf(link);
        const response = await fetch(link.dataset.detailUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        this.content = page.querySelector('main')?.innerHTML ?? '';
        this.$refs.dialog.showModal();
        this.$nextTick(() => this.$refs.dialog.querySelector('button, a')?.focus());
    },
    close() {
        this.$refs.dialog.close();
        this.returnFocus();
    },
    returnFocus() { this.trigger?.focus(); },
    previous() { if (this.index > 0) this.open(this.links[this.index - 1]); },
    next() { if (this.index < this.links.length - 1) this.open(this.links[this.index + 1]); },
    touchStart(event) { this.touchX = event.changedTouches[0]?.screenX ?? null; },
    touchEnd(event) {
        const end = event.changedTouches[0]?.screenX;
        if (this.touchX === null || end === undefined) return;
        if (end - this.touchX > 48) this.previous();
        if (this.touchX - end > 48) this.next();
        this.touchX = null;
    },
    trap(event) {
        const focusable = [...this.$refs.dialog.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')];
        if (focusable.length === 0) return;
        const current = focusable.indexOf(document.activeElement);
        const next = event.shiftKey ? (current <= 0 ? focusable.length - 1 : current - 1) : (current === focusable.length - 1 ? 0 : current + 1);
        focusable[next].focus();
    },
}));
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
