/**
 * NOOVA S.A.C. - Passive Router (GitHub Pages compatible)
 * Lightweight utility. Navigation uses native <a> tag full page loads.
 */
(function() {
  'use strict';

  const ROUTER = {

    go(url) {
      window.location.href = url;
    }
  };

  // Expose globally
  window.Router = ROUTER;

})();
