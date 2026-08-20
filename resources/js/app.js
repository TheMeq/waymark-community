//
import Alpine from 'alpinejs';
import siteBanner from './site-banner';

window.Alpine = Alpine;
document.documentElement.classList.add('js');

Alpine.data('siteBanner', siteBanner);

Alpine.start();
