/**
 * Meta Pixel — PageView al cargar. Lead se dispara desde lead-form.js.
 * El token de acceso NO va aquí: solo se usa en el servidor (Conversions API).
 */
(function () {
  window.DFSK_newEventId = function () {
    return 'lead_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
  };

  var pixelId = (window.DFSK_SITE && window.DFSK_SITE.pixelId) ? String(window.DFSK_SITE.pixelId).trim() : '';
  if (!pixelId) return;

  if (window.fbq) return;

  !function (f, b, e, v, n, t, s) {
    if (f.fbq) return;
    n = f.fbq = function () {
      n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
    };
    if (!f._fbq) f._fbq = n;
    n.push = n;
    n.loaded = !0;
    n.version = '2.0';
    n.queue = [];
    t = b.createElement(e);
    t.async = !0;
    t.src = v;
    s = b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t, s);
  }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

  window.fbq('init', pixelId);
  window.fbq('track', 'PageView');

  window.DFSK_trackLead = function (payload) {
    payload = payload || {};
    var params = {
      content_name: payload.modelo || 'DFSK',
      content_category: 'Camioneta DFSK'
    };
    var opts = payload.eventId ? { eventID: payload.eventId } : undefined;
    window.fbq('track', 'Lead', params, opts);
  };
})();
