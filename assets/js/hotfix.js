/**
 * Hotfix layer: provides missing helper functions referenced by app.js
 * without touching the main bundle. Safe no-op defaults + lightweight UI.
 */
(function(){
  window.escapeHtml = window.escapeHtml || function(s){
    s = (s === null || s === undefined) ? '' : String(s);
    return s.replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  };
  window.escAttr = window.escAttr || window.escapeHtml;

  // Provide missing renderTimeChipsForSheet to avoid runtime crash when opening edit sheet
  window.renderTimeChipsForSheet = window.renderTimeChipsForSheet || function(opts){
    var times = [];
    var selected = '';
    var inputSelector = '#sheet_time, #event_time, input[name="event_time"], input[name="time"], input[name="event_time_hhmm"]';
    var containerSelector = '#sheetTimeChips, .sheet-time-chips, .time-chips';

    if (Array.isArray(opts)) {
      times = opts;
    } else if (opts && typeof opts === 'object') {
      times = Array.isArray(opts.times) ? opts.times : [];
      selected = opts.selected || '';
      if (opts.inputSelector) inputSelector = opts.inputSelector;
      if (opts.containerSelector) containerSelector = opts.containerSelector;
    }

    if (!times.length) {
      var metaTimes = (window.SCHEDULE && window.SCHEDULE.meta && window.SCHEDULE.meta.time_corridors) || window.TIME_CORRIDORS || [];
      times = Array.isArray(metaTimes) ? metaTimes : [];
    }
    if (!times.length) return '';

    var html = '<div class="timechip-wrap">';
    for (var i=0;i<times.length;i++){
      var t = times[i];
      var active = (selected && t === selected) ? ' is-active' : '';
      html += '<button type="button" class="timechip'+active+'" data-time="'+window.escAttr(t)+'">'+window.escapeHtml(t)+'</button>';
    }
    html += '</div>';

    if (!window.__timechipBound) {
      document.addEventListener('click', function(e){
        var btn = e.target && e.target.closest ? e.target.closest('.timechip') : null;
        if (!btn) return;
        var t = btn.getAttribute('data-time') || '';
        var scope = btn.closest('.sheet, .modal, .dialog, body') || document;
        var input = scope.querySelector(inputSelector) || document.querySelector(inputSelector);
        if (input) {
          input.value = t;
          input.dispatchEvent(new Event('input', {bubbles:true}));
          input.dispatchEvent(new Event('change', {bubbles:true}));
        }
        var wrap = btn.closest('.timechip-wrap');
        if (wrap) {
          wrap.querySelectorAll('.timechip.is-active').forEach(function(x){x.classList.remove('is-active');});
        }
        btn.classList.add('is-active');
        e.preventDefault();
        e.stopPropagation();
      }, true);
      window.__timechipBound = true;
    }

    try{
      var container = document.querySelector(containerSelector);
      if (container && !container.dataset.timechipsInjected) {
        container.innerHTML = html;
        container.dataset.timechipsInjected = '1';
      }
    }catch(_e){}
    return html;
  };

  // Safe fallbacks if missing
  window.renderCarrierLogo = window.renderCarrierLogo || function(){ return ''; };
  window.renderCarrierLogos = window.renderCarrierLogos || function(){ return ''; };
})();
