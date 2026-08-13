/**
 * Envío de lead → API + WhatsApp.
 * options.fuente: 'landing' (Meta form) | 'formulario' (SEO) | 'whatsapp' (clic WA).
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
    if (btn) {
      var prev = btn.textContent;
      btn.textContent = 'Completa nombre y teléfono';
      btn.classList.add('bg-red-700');
      setTimeout(function () {
        btn.textContent = prev;
        btn.classList.remove('bg-red-700');
      }, 2500);
    }
    return;
  }

  if (btn) {
    btn.textContent = 'Enviando…';
    btn.disabled = true;
  }

  var notas = typeof options.notas === 'function' ? options.notas(form) : null;
  var waMsg = typeof options.waMsg === 'function'
    ? options.waMsg(form, nombre, telefono, modelo, origen)
    : 'Hola Felipe, me llamo ' + nombre + ' y quiero cotizar una DFSK. Mi teléfono es ' + telefono + '.';

  var eventId = typeof window.DFSK_newEventId === 'function'
    ? window.DFSK_newEventId()
    : 'lead_' + Date.now();

  if (typeof window.DFSK_trackLead === 'function') {
    window.DFSK_trackLead({ eventId: eventId, modelo: modelo });
  }

  fetch(API_LEADS, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      nombre: nombre,
      telefono: telefono,
      modelo: modelo || null,
      fuente: fuente,
      origen: origen,
      notas: notas || null,
      event_id: eventId,
      event_source_url: window.location.href
    })
  }).catch(function () {}).finally(function () {
    if (btn) {
      btn.textContent = 'Enviado — Abriendo WhatsApp…';
      btn.classList.add('opacity-70', 'cursor-not-allowed');
    }
    var waURL = SITE.waHref ? SITE.waHref(waMsg) : 'https://wa.me/' + SITE.phoneWa + '?text=' + encodeURIComponent(waMsg);
    setTimeout(function () {
      window.open(waURL, '_blank', 'noopener,noreferrer');
    }, 600);
  });
};

/**
 * Clic WhatsApp → Pixel Lead + CRM con fuente=whatsapp.
 * Si el form ya tiene nombre+teléfono, usa esos datos; si no, registra el clic igual.
 */
window.DFSK_whatsAppClick = function (e, form, options) {
  options = options || {};
  e.preventDefault();

  var API_LEADS = 'api/leads.php';
  var nombreEl = form ? form.querySelector('[name="nombre"], #nombre') : null;
  var telefonoEl = form ? form.querySelector('[name="telefono"], #telefono') : null;
  var origenEl = form ? form.querySelector('[name="origen"], #origen_lead') : null;
  var modeloEl = form ? form.querySelector('[name="modelo"], #modelo_lead') : null;
  var notasEl = form ? form.querySelector('[name="notas"], #notas_lead') : null;

  var nombre = nombreEl ? nombreEl.value.trim() : '';
  var telefono = telefonoEl ? telefonoEl.value.trim() : '';
  var origen = origenEl ? origenEl.value : (options.origen || 'Campana_MetaAds');
  var modelo = modeloEl ? modeloEl.value : (options.modelo || 'DFSK');
  var notasBase = notasEl ? notasEl.value : '';

  var eventId = typeof window.DFSK_newEventId === 'function'
    ? window.DFSK_newEventId()
    : 'lead_' + Date.now();

  if (typeof window.DFSK_trackLead === 'function') {
    window.DFSK_trackLead({ eventId: eventId, modelo: modelo });
  }

  var hasContact = !!(nombre && telefono);
  var payload = {
    nombre: hasContact ? nombre : 'Clic WhatsApp',
    telefono: hasContact ? telefono : 's/d',
    modelo: modelo || null,
    fuente: 'whatsapp',
    origen: origen,
    notas: [
      notasBase,
      hasContact ? 'Canal: WhatsApp (con datos)' : 'Canal: WhatsApp (clic sin formulario)',
      'Página: ' + window.location.pathname
    ].filter(Boolean).join(' | '),
    event_id: eventId,
    event_source_url: window.location.href
  };

  fetch(API_LEADS, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  }).catch(function () {}).finally(function () {
    var href = e.currentTarget.getAttribute('href');
    if (href) {
      window.open(href, '_blank', 'noopener,noreferrer');
    }
  });
};
