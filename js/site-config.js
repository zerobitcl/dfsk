/**
 * Configuración global — Felipe Callegari · DFSK Ovalle
 */
window.DFSK_SITE = {
  url: 'https://www.felipecallegari.cl',
  dealer: 'Callegari Automotriz',
  advisor: 'Felipe Núñez',
  phoneDisplay: '+56 9 8548 0881',
  phoneTel: '+56985480881',
  phoneWa: '56985480881',
  addressStreet: 'Covarrubias 340',
  addressCity: 'Ovalle',
  addressRegion: 'Coquimbo',
  addressPostal: '1840000',
  addressFull: 'Covarrubias 340, 1840000 Ovalle, Coquimbo',
  ogImage: 'https://www.felipecallegari.cl/assets/img/Banner.webp',
  contactUrl: 'index.html#contacto',
  telHref: function () {
    return 'tel:' + this.phoneTel;
  },
  waHref: function (message) {
    var base = 'https://wa.me/' + this.phoneWa;
    return message ? base + '?text=' + encodeURIComponent(message) : base;
  }
};
