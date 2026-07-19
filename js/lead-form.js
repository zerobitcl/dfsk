/**
 * Envío de lead → API + WhatsApp.
 * options.fuente: 'landing' (Meta) | 'formulario' (SEO/home). Default: landing.
 */
window.DFSK_submitLead = function (form, options) {
  options = options || {};
  var API_LEADS = 'api/leads.php';
  var SITE = window.DFSK_SITE || { phoneWa: '56985480881' };
  var btn = form.querySelector('button[type="submit"]');
  var nombreEl = form.querySelector('[name="nombre"], #nombre');
  var telefonoEl = form.querySelector('[name="telefono"], #telefono');
  var origenEl = form.querySelector('[name="origen"], #origen_lead');
  var modeloEl = form.querySelector('[name="modelo"], #modelo_lead');
  var nombre = nombreEl ? nombreEl.value.trim() : '';
  var telefono = telefonoEl ? telefonoEl.value.trim() : '';
  var origen = origenEl ? origenEl.value : '';
  var modelo = modeloEl ? modeloEl.value : '';
  var fuente = options.fuente || 'landing';

  if (!nombre || !telefono) {
    var prev = btn.textContent;
    btn.textContent = 'Completa nombre y teléfono';
    btn.classList.add('bg-red-700');
    setTimeout(function () {
      btn.textContent = prev;
      btn.classList.remove('bg-red-700');
    }, 2500);
    return;
  }

  btn.textContent = 'Enviando…';
  btn.disabled = true;

  var notas = typeof options.notas === 'function' ? options.notas(form) : null;
  var waMsg = typeof options.waMsg === 'function'
    ? options.waMsg(form, nombre, telefono, modelo, origen)
    : 'Hola Felipe, me llamo ' + nombre + ' y quiero cotizar una DFSK. Mi teléfono es ' + telefono + '.';

  fetch(API_LEADS, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      nombre: nombre,
      telefono: telefono,
      modelo: modelo || null,
      fuente: fuente,
      origen: origen,
      notas: notas || null
    })
  }).catch(function () {}).finally(function () {
    btn.textContent = '✓ Enviado — Abriendo WhatsApp…';
    btn.classList.add('opacity-70', 'cursor-not-allowed');
    var waURL = SITE.waHref ? SITE.waHref(waMsg) : 'https://wa.me/' + SITE.phoneWa + '?text=' + encodeURIComponent(waMsg);
    setTimeout(function () {
      window.open(waURL, '_blank', 'noopener,noreferrer');
    }, 600);
  });
};
