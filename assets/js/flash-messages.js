(function (window) {
  'use strict';

  var pending = [];
  var processing = false;

  function stripHtml(input) {
    return String(input == null ? '' : input).replace(/<[^>]+>/g, ' ').trim();
  }

  function mapType(type) {
    switch ((type || 'info').toString().toLowerCase()) {
      case 'success':
        return { icon: 'success', title: 'Todo listo' };
      case 'error':
      case 'danger':
        return { icon: 'error', title: 'Hubo un problema' };
      case 'warning':
        return { icon: 'warning', title: 'Atención' };
      default:
        return { icon: 'info', title: 'Aviso' };
    }
  }

  function buildSweetAlertOptions(type, message, title, options) {
    var mapped = mapType(type);
    var payloadText = message == null ? '' : String(message);
    var isHtml = /<[a-z][\s\S]*>/i.test(payloadText);
    var baseOptions = {
      icon: mapped.icon,
      title: title && title !== '' ? title : mapped.title,
      confirmButtonText: 'Aceptar',
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    };
    if (isHtml) {
      baseOptions.html = payloadText;
    } else {
      baseOptions.text = payloadText;
    }
    if (options && typeof options === 'object') {
      var merged = Object.assign({}, options);
      if (merged.customClass && typeof merged.customClass === 'object') {
        baseOptions.customClass = Object.assign({}, baseOptions.customClass, merged.customClass);
        delete merged.customClass;
      }
      baseOptions = Object.assign(baseOptions, merged);
    }
    return baseOptions;
  }

  function showFallback(type, message, title) {
    var mapped = mapType(type);
    var payloadText = message == null ? '' : String(message);
    var composed = (title && title !== '' ? title : mapped.title) + '\n' + stripHtml(payloadText);
    var text = composed.trim();
    if (text !== '') {
      window.alert(text);
    }
  }

  function processQueue(attempt) {
    if (!pending.length) {
      processing = false;
      return;
    }

    if (typeof Swal === 'undefined') {
      if (attempt < 30) {
        setTimeout(function () {
          processQueue(attempt + 1);
        }, 100);
        return;
      }
      while (pending.length) {
        var fallbackItem = pending.shift();
        showFallback(fallbackItem.type, fallbackItem.message, fallbackItem.title);
      }
      processing = false;
      return;
    }

    var item = pending.shift();
    var options = buildSweetAlertOptions(item.type, item.message, item.title, item.options);
    Swal.fire(options).then(function () {
      processQueue(0);
    });
  }

  function startQueue() {
    if (processing || !pending.length) {
      return;
    }
    processing = true;
    processQueue(0);
  }

  function collectFromDom() {
    var nodes = document.querySelectorAll('script[type="application/json"][data-flash]');
    nodes.forEach(function (node) {
      if (!node) {
        return;
      }
      var text = node.textContent || node.innerText || '';
      try {
        var data = JSON.parse(text);
        if (data && data.message) {
          pending.push({
            type: data.type || data.icon || 'info',
            title: data.title || '',
            message: data.message,
            options: data.options || null
          });
        }
      } catch (err) {
        if (text) {
          pending.push({ type: 'info', title: 'Aviso', message: text, options: null });
        }
      }
      if (node.parentNode) {
        node.parentNode.removeChild(node);
      }
    });
  }

  function init() {
    collectFromDom();
    startQueue();
  }

  window.AppAlerts = {
    show: function (type, message, title, options) {
      pending.push({ type: type, message: message, title: title || '', options: options || null });
      startQueue();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
