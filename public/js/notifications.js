/**
 * Orion - Notifications dropdown
 * Fetches notifications on bell click, mark-as-read, offer actions (Accept/Decline/Negotiate), polling for badge.
 */
(function () {
  'use strict';

  // Turbo can re-evaluate body scripts on navigation. Keep one poller/listener set globally.
  if (window.__orionNotificationDropdownInit === true) {
    return;
  }
  window.__orionNotificationDropdownInit = true;

  var DROPDOWN_URL = '/notifications/dropdown';
  var POLL_INTERVAL_MS = 15000;
  var POLL_TIMEOUT_MS = 10000;
  var pollTimer = null;
  var pollInFlight = false;

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  function fetchOptions(method, body) {
    var opts = {
      method: method,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    };
    if (body !== undefined) {
      opts.body = typeof body === 'string' ? body : JSON.stringify(body);
    }
    var csrf = getCsrfToken();
    if (csrf) {
      opts.headers['X-CSRF-Token'] = csrf;
    }
    return opts;
  }

  function updateBadge(count) {
    var badge = document.getElementById('notification-badge');
    if (!badge) return;
    var n = parseInt(count, 10) || 0;
    if (n > 0) {
      badge.textContent = n > 99 ? '99+' : n;
      badge.style.display = '';
    } else {
      badge.style.display = 'none';
    }
  }

  function loadDropdownContent() {
    var container = document.getElementById('notification-dropdown-content');
    if (!container) return;

    container.innerHTML = '<div class="flex items-center justify-center py-8 text-slate-400"><span class="text-sm">Loading...</span></div>';

    fetch(DROPDOWN_URL + '?format=html', {
      method: 'GET',
      headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Load failed');
        return res.text();
      })
      .then(function (html) {
        container.innerHTML = html;
        var root = container.querySelector('[data-unread-count]');
        if (root) {
          updateBadge(root.getAttribute('data-unread-count'));
        }
        bindOfferActions(container);
        bindMarkRead(container);
      })
      .catch(function () {
        container.innerHTML = '<div class="flex items-center justify-center py-8 text-slate-500"><span class="text-sm">Could not load notifications.</span></div>';
      });
  }

  function bindMarkRead(container) {
    if (!container || container.dataset.markReadBound === '1') return;
    container.dataset.markReadBound = '1';

    container.addEventListener('click', function (e) {
      var item = e.target.closest('.notification-item');
      if (!item) return;
      var id = item.getAttribute('data-notification-id');
      if (!id) return;
      fetch('/notifications/' + id + '/read', fetchOptions('POST'))
        .then(function (res) {
          return res.ok ? res.json() : null;
        })
        .then(function () {
          item.querySelector('[class*="bg-slate-50"], [class*="bg-slate-800"]')?.classList.remove('bg-slate-50', 'dark:bg-slate-800/50');
          var badge = document.getElementById('notification-badge');
          if (badge && badge.textContent) {
            var n = parseInt(badge.textContent, 10) - 1;
            updateBadge(Math.max(0, n));
          }
        });
    });
  }

  function bindOfferActions(container) {
    if (!container || container.dataset.offerActionsBound === '1') return;
    container.dataset.offerActionsBound = '1';

    container.addEventListener('click', function (e) {
      var offerRoot = e.target.closest('[data-offer-id]');
      var offerId = offerRoot ? offerRoot.getAttribute('data-offer-id') : null;
      if (!offerId) return;

      var acceptBtn = e.target.closest('.offer-accept');
      var declineBtn = e.target.closest('.offer-decline');
      var negotiateBtn = e.target.closest('.offer-negotiate');
      var negotiateSubmitBtn = e.target.closest('.negotiate-submit');

      if (acceptBtn) {
        e.preventDefault();
        e.stopPropagation();
        postOfferAction(offerId, 'accept', null, acceptBtn.closest('.notification-item'));
        return;
      }
      if (declineBtn) {
        e.preventDefault();
        e.stopPropagation();
        postOfferAction(offerId, 'decline', null, declineBtn.closest('.notification-item'));
        return;
      }
      if (negotiateBtn) {
        e.preventDefault();
        e.stopPropagation();
        var item = negotiateBtn.closest('.notification-item');
        var form = item?.querySelector('.negotiate-form');
        if (form) {
          form.classList.toggle('hidden');
        }
        return;
      }
      if (negotiateSubmitBtn) {
        e.preventDefault();
        e.stopPropagation();
        var item = negotiateSubmitBtn.closest('.notification-item');
        var form = item?.querySelector('.negotiate-form');
        var budget = form?.querySelector('[name="budget"]')?.value;
        var deadline = form?.querySelector('[name="deadline"]')?.value;
        var message = form?.querySelector('[name="message"]')?.value;
        postOfferAction(offerId, 'negotiate', { budget: budget || null, deadline: deadline || null, message: message || null }, item);
        if (form) form.classList.add('hidden');
      }
    });
  }

  function postOfferAction(offerId, action, body, notificationItem) {
    // Offer actions are served by the unified API controller for both client and worker roles.
    var url = '/api/offers/' + offerId + '/' + action;
    var opts = fetchOptions('POST', body || undefined);

    fetch(url, opts)
      .then(function (res) {
        return res
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            return { ok: res.ok, status: res.status, data: data };
          });
      })
      .then(function (result) {
        var data = result.data || {};

        if (!result.ok || !data.success) {
          var msg = data.error || data.message || 'Request failed (' + result.status + ')';
          if (typeof window !== 'undefined' && window.alert) {
            alert(msg);
          }
        }

        if (data.success && notificationItem) {
          if (action === 'decline' || action === 'accept') {
            var listItem = notificationItem.closest ? notificationItem.closest('li') : notificationItem;
            if (!listItem) listItem = notificationItem;
            if (listItem.parentNode) {
              listItem.parentNode.removeChild(listItem);
            }
            var badge = document.getElementById('notification-badge');
            if (badge && badge.textContent) {
              var n = parseInt(badge.textContent, 10) - 1;
              updateBadge(Math.max(0, n));
            }
          } else {
            var actions = notificationItem.querySelector('.offer-actions');
            if (actions) {
              actions.innerHTML = '<span class="text-xs font-medium text-slate-500">' + (data.status || action) + '</span>';
            }
            var form = notificationItem.querySelector('.negotiate-form');
            if (form) form.classList.add('hidden');
          }
        }
        if (result.ok || result.status === 400) {
          if (window.NotificationDropdown && window.NotificationDropdown.load) {
            window.NotificationDropdown.load();
          }
        }
      });
  }

  function scheduleNextPoll(delayMs) {
    if (pollTimer) {
      clearTimeout(pollTimer);
    }
    pollTimer = setTimeout(function () {
      pollUnreadCount();
    }, delayMs);
  }

  function pollUnreadCount() {
    if (pollInFlight) {
      scheduleNextPoll(POLL_INTERVAL_MS);
      return;
    }
    if (typeof document !== 'undefined' && document.hidden) {
      scheduleNextPoll(POLL_INTERVAL_MS);
      return;
    }

    pollInFlight = true;
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timeoutId = null;
    var opts = {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    };
    if (controller) {
      opts.signal = controller.signal;
      timeoutId = setTimeout(function () {
        controller.abort();
      }, POLL_TIMEOUT_MS);
    }

    fetch(DROPDOWN_URL + '?only_count=1', opts)
      .then(function (res) {
        return res.ok ? res.json() : null;
      })
      .then(function (data) {
        if (data && typeof data.unread_count !== 'undefined') {
          updateBadge(data.unread_count);
        }
      })
      .catch(function () {})
      .finally(function () {
        if (timeoutId) {
          clearTimeout(timeoutId);
        }
        pollInFlight = false;
        scheduleNextPoll(POLL_INTERVAL_MS);
      });
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
      scheduleNextPoll(0);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var panel = document.getElementById('notification-dropdown-panel');
      if (panel && panel.getAttribute('aria-hidden') !== 'true') {
        var wrap = document.querySelector('[data-notification-bell-wrap]');
        if (wrap && typeof wrap.__x !== 'undefined' && wrap.__x.$data) {
          wrap.__x.$data.open = false;
        }
      }
    }
  });

  window.NotificationDropdown = {
    load: loadDropdownContent,
    updateBadge: updateBadge,
    pollNow: function () {
      scheduleNextPoll(0);
    }
  };

  scheduleNextPoll(0);
})();
