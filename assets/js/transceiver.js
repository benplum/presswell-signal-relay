'use strict';
(function (window, document) {
  if (!window || !document) {
    return;
  }

  var config = window.PWTSR_CONFIG || {};
  var storageKey = config.storageKey || 'pwSignalRelay';
  var ttlMs = Math.max(parseInt(config.ttl, 10) || 0, 0) * 1000;
  var transceiverKeys = Array.isArray(config.transceiverKeys) ? config.transceiverKeys : [];
  var derivedKeys = Array.isArray(config.derivedKeys) ? config.derivedKeys : ['landing_page', 'landing_query', 'referrer'];
  var attributionModel = ['both', 'last', 'first'].indexOf(config.attributionModel) !== -1 ? config.attributionModel : 'both';
  var firstTouch = attributionModel === 'both';
  var firstTouchPrefix = config.firstTouchPrefix || 'first_';
  var requireConsent = config.requireConsent === true;
  var consentCategory = config.consentCategory || 'marketing';

  // Consent given through setConsent() or the WP Consent API event; null until one arrives.
  var consentGiven = null;
  var initialized = false;

  function hasConsent() {
    if (!requireConsent) {
      return true;
    }

    if (consentGiven !== null) {
      return consentGiven;
    }

    // Set by consent scripts that run before this one.
    if (window.presswellSignalRelayConsent === true) {
      return true;
    }

    if (typeof window.wp_has_consent === 'function') {
      return !!window.wp_has_consent(consentCategory);
    }

    return false;
  }

  function encodePayload(payload) {
    try {
      var json = JSON.stringify(payload);
      if (typeof window.TextEncoder === 'function') {
        var bytes = new TextEncoder().encode(json);
        var binary = '';
        bytes.forEach(function (byte) {
          binary += String.fromCharCode(byte);
        });
        return window.btoa(binary);
      }
      return window.btoa(unescape(encodeURIComponent(json)));
    } catch (err) {
      return null;
    }
  }

  function decodePayload(raw) {
    if (!raw) {
      return null;
    }

    try {
      var binary = window.atob(raw);
      var json;
      if (typeof window.TextDecoder === 'function') {
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
          bytes[i] = binary.charCodeAt(i);
        }
        json = new TextDecoder().decode(bytes);
      } else {
        json = decodeURIComponent(escape(binary));
      }
      return JSON.parse(json);
    } catch (err) {
      try {
        return JSON.parse(raw);
      } catch (innerErr) {
        return null;
      }
    }
  }

  function readStorage() {
    try {
      var stored = decodePayload(window.localStorage.getItem(storageKey));
      if (!stored || typeof stored !== 'object') {
        return null;
      }

      var nowTs = Date.now();
      if (ttlMs > 0 && stored.timestamp && nowTs - stored.timestamp > ttlMs) {
        window.localStorage.removeItem(storageKey);
        return null;
      }

      return stored;
    } catch (err) {
      return null;
    }
  }

  function writeStorage(data) {
    try {
      data.timestamp = Date.now();
      var encoded = encodePayload(data);
      if (encoded) {
        window.localStorage.setItem(storageKey, encoded);
      } else {
        window.localStorage.setItem(storageKey, JSON.stringify(data));
      }
    } catch (err) {
      // no-op
    }
  }

  function getQueryValues() {
    var values = {};
    if (!transceiverKeys.length) {
      return values;
    }

    var params = new URLSearchParams(window.location.search || '');

    // Keys are stored lowercase, but links arrive as UTM_Source or utm_Source too. Match
    // names in any case, preferring an exact match; values keep their case.
    var byLowerName = {};
    params.forEach(function (paramValue, name) {
      var lower = name.toLowerCase();
      if (!(lower in byLowerName) || name === lower) {
        byLowerName[lower] = paramValue;
      }
    });

    transceiverKeys.forEach(function (key) {
      // Derived keys describe the visit itself, so a query parameter must not override them.
      if (!key || derivedKeys.indexOf(key) !== -1) {
        return;
      }

      var paramValue = byLowerName[key.toLowerCase()];
      if (paramValue !== undefined && paramValue !== null && paramValue !== '') {
        values[key] = paramValue;
      }
    });

    return values;
  }

  function hasFreshTrackedValues(fresh) {
    return !!(fresh && Object.keys(fresh).length);
  }

  function ensureDerivedValues(values) {
    var updated = Object.assign({}, values);
    if (!updated.landing_page) {
      updated.landing_page = window.location.href;
    }
    if (!updated.landing_query) {
      updated.landing_query = window.location.search || '';
    }
    if (!updated.referrer && document.referrer) {
      updated.referrer = document.referrer;
    }
    return updated;
  }

  function isExternalReferrer(referrer) {
    if (!referrer) {
      return false;
    }

    try {
      return new URL(referrer).host !== window.location.host;
    } catch (err) {
      return false;
    }
  }

  // A direct visit (typed URL, bookmark, internal link) has no tracked parameters and no
  // outside referrer. It records where the visitor landed but says nothing about the source.
  function isDirectVisit(values) {
    var tagged = Object.keys(values || {}).some(function (key) {
      return derivedKeys.indexOf(key) === -1 && values[key] !== undefined && values[key] !== null && values[key] !== '';
    });

    return !tagged && !isExternalReferrer(values && values.referrer);
  }

  function stripFirstTouch(values) {
    var plain = {};
    Object.keys(values).forEach(function (key) {
      plain[key.slice(firstTouchPrefix.length)] = values[key];
    });

    return plain;
  }

  function isFirstTouchKey(key) {
    return key.indexOf(firstTouchPrefix) === 0;
  }

  // Keys the forms render: each capture key, then its first-touch copy.
  function getFieldKeys() {
    var keys = transceiverKeys.filter(Boolean);
    if (!firstTouch) {
      return keys;
    }

    return keys.concat(keys.map(function (key) {
      return firstTouchPrefix + key;
    }));
  }

  function pickValues(stored, wantFirstTouch) {
    var picked = {};
    Object.keys(stored || {}).forEach(function (key) {
      if (key === 'timestamp' || isFirstTouchKey(key) !== wantFirstTouch) {
        return;
      }

      picked[key] = stored[key];
    });

    return picked;
  }

  function toFirstTouch(values) {
    var copy = {};
    Object.keys(values).forEach(function (key) {
      copy[firstTouchPrefix + key] = values[key];
    });

    return copy;
  }

  function populateInputs(values) {
    if (!values) {
      return;
    }

    var inputs = document.querySelectorAll('[data-pwtsr-transceiver]');
    if (!inputs.length) {
      return;
    }

    inputs.forEach(function (input) {
      var key = input.getAttribute('data-pwtsr-transceiver');
      if (!key) {
        return;
      }
      input.value = values[key] || '';
    });
  }

  function injectForminatorFallbackInputs() {
    if (!transceiverKeys.length) {
      return;
    }

    var forms = document.querySelectorAll('.forminator-ui form');
    if (!forms.length) {
      return;
    }

    forms.forEach(function (form) {
      if (form.querySelector('[data-pwtsr-transceiver-adapter="forminator"]')) {
        return;
      }

      var wrapper = document.createElement('div');
      wrapper.className = 'pwtsr-transceiver pwtsr-forminator-transceiver';
      wrapper.setAttribute('data-pwtsr-transceiver', '1');
      wrapper.setAttribute('data-pwtsr-transceiver-adapter', 'forminator');
      wrapper.setAttribute('aria-hidden', 'true');
      wrapper.style.display = 'none';

      getFieldKeys().forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = '';
        input.setAttribute('data-pwtsr-transceiver', key);
        wrapper.appendChild(input);
      });

      form.appendChild(wrapper);
    });
  }

  // Debug display only: explain empty fields while consent is outstanding.
  function updateConsentNotice() {
    var waiting = !hasConsent();
    document.querySelectorAll('.pwtsr-debug-consent').forEach(function (notice) {
      notice.hidden = !waiting;
    });
  }

  function clearSignals() {
    try {
      window.localStorage.removeItem(storageKey);
    } catch (err) {
      // no-op
    }

    populateInputs({});
  }

  function syncSignals() {
    updateConsentNotice();

    if (!hasConsent()) {
      return;
    }

    var stored = readStorage() || {};
    var fresh = getQueryValues();

    var current = pickValues(stored, false);
    var hasRecord = Object.keys(current).length > 0;
    var thisVisit = ensureDerivedValues(fresh);

    // First touch is the first visit with a source. A direct visit holds the spot only until
    // a tagged or externally referred visit arrives.
    function claimsFirstTouch(firstValues) {
      return !Object.keys(firstValues).length || (isDirectVisit(firstValues) && !isDirectVisit(thisVisit));
    }

    // The plain keys follow the chosen model. Last touch: a page with tracked query params
    // replaces the record, untagged pages keep it. First touch: kept until it expires.
    var replace = attributionModel === 'first' ? claimsFirstTouch(current) : hasFreshTrackedValues(fresh) || !hasRecord;
    var values = replace ? thisVisit : ensureDerivedValues(current);
    var merged = Object.assign({}, values);

    // With the 'both' model, the first_ copies hold the first-touch values.
    if (firstTouch) {
      var firstValues = pickValues(stored, true);
      Object.assign(merged, claimsFirstTouch(stripFirstTouch(firstValues)) ? toFirstTouch(thisVisit) : firstValues);
    }

    writeStorage(merged);

    injectForminatorFallbackInputs();
    populateInputs(merged);
  }

  // Withdrawn consent removes what was stored; granted consent captures the current page.
  function applyConsent(granted) {
    consentGiven = !!granted;

    if (!initialized) {
      return;
    }

    if (consentGiven) {
      syncSignals();
    } else {
      clearSignals();
      updateConsentNotice();
    }
  }

  window.presswellSignalRelay = {
    setConsent: applyConsent,
    hasConsent: hasConsent
  };

  // WP Consent API: https://wordpress.org/plugins/wp-consent-api/
  document.addEventListener('wp_listen_for_consent_change', function (event) {
    var changed = event && event.detail ? event.detail : {};
    if (Object.prototype.hasOwnProperty.call(changed, consentCategory)) {
      applyConsent(changed[consentCategory] === 'allow');
    }
  });

  function init() {
    initialized = true;

    // Without consent this does nothing; an explicit withdrawal is what clears the record.
    syncSignals();

    if (typeof MutationObserver !== 'function') {
      return;
    }

    // Forms can render late (AJAX, popups), but the script now runs on every page,
    // so batch bursts of DOM changes into a single sync.
    var syncTimer = null;
    var observer = new MutationObserver(function () {
      if (syncTimer) {
        return;
      }

      syncTimer = window.setTimeout(function () {
        syncTimer = null;
        syncSignals();
      }, 100);
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
