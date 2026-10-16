(function () {
  'use strict';

  var config = window.DhikraApp || {};
  var widgets = document.querySelectorAll('[data-dhikra-widget]');

  function setupEitaaMiniApp() {
    var webApp = window.Eitaa && window.Eitaa.WebApp;
    if (!webApp || typeof webApp.ready !== 'function') return null;
	var launchSource = (window.location.search || '').replace(/^\?/, '') + '&' + (window.location.hash || '').replace(/^#/, '');
	var launchParams = new URLSearchParams(launchSource);
	var launchedInsideEitaa = Boolean(webApp.initData) || (webApp.platform && webApp.platform !== 'unknown') || launchParams.has('tgWebAppData') || launchParams.has('tgWebAppVersion');
	if (!launchedInsideEitaa) return null;

    var root = document.documentElement;
    root.classList.add('dhikra-eitaa');

    var syncTheme = function () {
      var isDark = webApp.colorScheme === 'dark';
      root.dataset.eitaaTheme = isDark ? 'dark' : 'light';
      try {
        if (typeof webApp.setHeaderColor === 'function') webApp.setHeaderColor(isDark ? '#0F1C2E' : '#1F63C3');
        if (typeof webApp.setBackgroundColor === 'function') webApp.setBackgroundColor(isDark ? '#0B1422' : '#EEF4FB');
        if (typeof webApp.setBottomBarColor === 'function') webApp.setBottomBarColor(isDark ? '#0B1422' : '#EEF4FB');
      } catch (error) {}
    };
    var syncViewport = function () {
      var height = Number(webApp.viewportStableHeight || webApp.viewportHeight || 0);
      if (height > 0) root.style.setProperty('--dhikra-viewport-height', height + 'px');
    };
    var syncSafeArea = function () {
      var inset = webApp.contentSafeAreaInset || webApp.safeAreaInset || {};
      ['top', 'right', 'bottom', 'left'].forEach(function (side) {
        var value = Math.max(0, Number(inset[side] || 0));
        root.style.setProperty('--dhikra-safe-' + side, value + 'px');
      });
    };

    try {
      syncTheme();
      syncViewport();
      syncSafeArea();
      var cardRoute = !config.route || config.route === 'home' || config.route === 'telangor';
      if (cardRoute && typeof webApp.disableVerticalSwipes === 'function') webApp.disableVerticalSwipes();
      if (!cardRoute && typeof webApp.enableVerticalSwipes === 'function') webApp.enableVerticalSwipes();
      if (typeof webApp.expand === 'function') webApp.expand();

      if (typeof webApp.onEvent === 'function') {
        webApp.onEvent('themeChanged', syncTheme);
        webApp.onEvent('viewportChanged', syncViewport);
        webApp.onEvent('safeAreaChanged', syncSafeArea);
        webApp.onEvent('contentSafeAreaChanged', syncSafeArea);
        webApp.onEvent('activated', function () {
          root.classList.add('dhikra-eitaa-active');
          syncViewport();
          window.dispatchEvent(new Event('dhikra:eitaa-activated'));
        });
        webApp.onEvent('deactivated', function () { root.classList.remove('dhikra-eitaa-active'); });
      }

      if (webApp.BackButton) {
        if (config.route && config.route !== 'home') {
          webApp.BackButton.show();
          webApp.BackButton.onClick(function () { window.location.href = config.homeUrl || '/'; });
        } else {
          webApp.BackButton.hide();
        }
      }
      webApp.ready();
    } catch (error) {
      // The public web version must continue to work when an older Eitaa
      // client exposes only part of the current SDK.
    }

    return webApp;
  }

  var eitaaWebApp = setupEitaaMiniApp();

  async function apiData(response, context) {
    var data = null;
    try { data = await response.json(); } catch (error) { data = {}; }
    if (!response.ok) {
      var detail = { context: context, status: response.status, code: data && data.code, message: data && data.message };
      if (window.console && console.error) console.error('Dhikra REST error', detail);
      throw new Error(data && data.message ? data.message : 'Request failed');
    }
    return data;
  }

  async function eitaaPost(path, payload) {
    var response = await fetch(config.restUrl + path, {
      method: 'POST',
      credentials: 'omit',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return apiData(response, path);
  }

  function showReward(widget, reward) {
    var box = widget.querySelector('[data-dhikra-reward-result]');
    var target = widget.querySelector('[data-dhikra-reward-text]');
    if (!box || !target || !reward || !reward.winner_message) return;
    var seenKey = 'dhikra_reward_seen_' + String(reward.campaign_id || '0');
    try { if (!reward.created && window.localStorage.getItem(seenKey) === '1') return; window.localStorage.setItem(seenKey, '1'); } catch (error) {}
    target.textContent = reward.winner_message;
    var campaign = box.querySelector('[data-dhikra-reward-campaign]');
    if (campaign) campaign.textContent = reward.campaign_title || '';
    var cta = box.querySelector('[data-dhikra-reward-cta]');
    if (cta && reward.winner_cta_url) {
      cta.href = reward.winner_cta_url;
      cta.textContent = reward.winner_cta_label || 'مشاهده اطلاعات جایزه';
      cta.hidden = false;
    }
    box.dataset.shareText = 'من در پویش «' + String(reward.campaign_title || 'ذکری') + '» برنده شدم.\n' + String(reward.winner_message || '');
    box.hidden = false;
    document.body.classList.add('dhikra-has-dialog');
    var close = box.querySelector('[data-dhikra-reward-close]');
    if (close) close.focus();
  }

  function requestWinnerWriteAccess(widget, reward) {
    if (!widget || !reward || !reward.needs_write_access || !eitaaWebApp) return;
    if (widget.dataset.winnerWriteAccessRequested === 'true') return;
    widget.dataset.winnerWriteAccessRequested = 'true';
    requestEitaaMessagePermission(widget, async function (granted) {
      if (!granted) {
        message(widget, 'برای دریافت پیام جایزه در ایتا، اجازه ارسال پیام لازم است.');
        return;
      }
      try {
        var state = await eitaaPost('eitaa/write-access', { init_data: eitaaWebApp.initData, granted: true });
        var deliveries = state.deliveries || [];
        var failed = deliveries.filter(function (item) { return item.status !== 'sent'; });
        if (state.daily_enabled) {
          widget.dataset.dailyEnabled = 'true';
          updateDailyShortLabel(widget);
        }
        if (failed.length) message(widget, failed[0].error || 'مجوز ثبت شد و تلنگر روزانه فعال شد؛ اما ارسال پیام جایزه ناموفق بود.');
        else message(widget, 'مجوز پیام ثبت شد و تلنگر روزانه فعال است.');
      } catch (error) {
        message(widget, error.message || 'ثبت مجوز ارسال پیام انجام نشد.');
      }
    });
  }

  // Daily reminders use one global admin time and all active topics.
  // First-launch consent is handled by Eitaa's native write-access dialog.
  var permissionRequestPending = false;
  function requestEitaaMessagePermission(widget, callback) {
    if (widget.dataset.dailyAllowsWrite === 'true') { callback(true, false); return; }
    if (permissionRequestPending) return;
    if (!eitaaWebApp || typeof eitaaWebApp.requestWriteAccess !== 'function') {
      message(widget, 'نسخه فعلی ایتا امکان درخواست مجوز پیام را ارائه نکرده است.');
      return;
    }
    permissionRequestPending = true;
    try {
      eitaaWebApp.requestWriteAccess(function (granted) {
        permissionRequestPending = false;
        if (granted === true) {
          widget.dataset.dailyAllowsWrite = 'true';
          callback(true, true);
        } else {
          message(widget, 'مجوز پیام ایتا تأیید نشد؛ تلنگر روزانه در ذِكْرَىٰ روشن می‌ماند اما تا زمان تأیید مجوز، پیامی ارسال نمی‌شود.');
          callback(false, false);
        }
      });
    } catch (error) {
      permissionRequestPending = false;
      message(widget, 'درخواست مجوز ایتا انجام نشد.');
    }
  }

  function updateDailyShortLabel(widget) {
    var label = widget && widget.querySelector('[data-dhikra-daily-short-label]');
    if (!label) return;
    var enabled = widget.dataset.dailyEnabled === 'true';
    var permitted = widget.dataset.dailyAllowsWrite === 'true';
    label.textContent = enabled && permitted ? 'روزانه ✓' : (enabled ? 'روزانه !' : 'روزانه');
  }

  async function saveDailyPreference(widget, enabled, writeGranted, source) {
    var panel = source || widget.querySelector('[data-dhikra-daily-settings]');
    var buttons = panel ? panel.querySelectorAll('button') : [];
    Array.prototype.forEach.call(buttons, function (button) { button.disabled = true; });
    try {
      var state = await eitaaPost('eitaa/daily', {
        init_data: eitaaWebApp.initData,
        enabled: enabled,
        write_access_granted: Boolean(writeGranted)
      });
      widget.dataset.dailyEnabled = state.enabled ? 'true' : 'false';
      widget.dataset.dailyOptedOut = state.enabled ? 'false' : 'true';
      if (state.allows_write_to_pm) widget.dataset.dailyAllowsWrite = 'true';
      widget.dataset.dailySendTime = String(state.daily_send_time || '08:00');
      updateDailyShortLabel(widget);
      var settings = widget.querySelector('[data-dhikra-daily-settings]');
      if (settings) settings.hidden = true;
      document.body.classList.remove('dhikra-has-dialog');
      message(widget, enabled ? 'تلنگر روزانه فعال شد.' : 'دریافت تلنگر روزانه متوقف شد.');
    } catch (error) {
      message(widget, error.message || 'ذخیره تنظیم انجام نشد.');
    } finally {
      Array.prototype.forEach.call(buttons, function (button) { button.disabled = false; });
    }
  }

  function autoEnrollDailyOnFirstLaunch(widget, state) {
    if (!widget || !state || !eitaaWebApp || !eitaaWebApp.initData) return;
    if (state.daily_opted_out) return;
    if (widget.dataset.autoDailyEnrollmentStarted === 'true') return;
    widget.dataset.autoDailyEnrollmentStarted = 'true';

    // Daily is the default Dhikra preference. If Eitaa already reports native
    // write permission, nothing else is required. Otherwise ask immediately.
    // A denial is NOT treated as an in-app opt-out; only the user's manual
    // switch in Daily settings can turn the subscription off.
    if (state.allows_write_to_pm) {
      if (state.daily_enabled) return;
      eitaaPost('eitaa/daily', {
        init_data: eitaaWebApp.initData,
        enabled: true,
        write_access_granted: false
      }).then(function (daily) {
        widget.dataset.dailyEnabled = daily.enabled ? 'true' : 'false';
        widget.dataset.dailyAllowsWrite = daily.allows_write_to_pm ? 'true' : 'false';
        widget.dataset.dailySendTime = String(daily.daily_send_time || '08:00');
        updateDailyShortLabel(widget);
      }).catch(function (error) {
        if (window.console && console.warn) console.warn('Dhikra automatic daily enable:', error.message);
      });
      return;
    }

    window.setTimeout(function () {
      requestEitaaMessagePermission(widget, async function (granted) {
        if (!granted) {
          widget.dataset.dailyEnabled = 'true';
          widget.dataset.dailyAllowsWrite = 'false';
          updateDailyShortLabel(widget);
          message(widget, 'تلنگر روزانه به‌صورت پیش‌فرض روشن است؛ برای دریافت واقعی پیام، مجوز ایتا را تأیید کنید.');
          return;
        }
        try {
          var access = await eitaaPost('eitaa/write-access', { init_data: eitaaWebApp.initData, granted: true });
          widget.dataset.dailyAllowsWrite = access.allows_write_to_pm ? 'true' : 'false';
          widget.dataset.dailyEnabled = access.daily_enabled ? 'true' : 'false';
          updateDailyShortLabel(widget);
          if (access.daily_enabled) message(widget, 'تلنگر روزانه فعال است و برای شما ارسال می‌شود.');
        } catch (error) {
          message(widget, error.message || 'ثبت مجوز دریافت تلنگر روزانه انجام نشد.');
        }
      });
    }, 220);
  }

  function eitaaVisitToken() {
    var key = 'dhikra_eitaa_visit_token_v2';
    var authDate = '';
    try { authDate = new URLSearchParams(String(eitaaWebApp && eitaaWebApp.initData || '')).get('auth_date') || ''; } catch (error) {}
    try {
      var raw = sessionStorage.getItem(key);
      if (raw) {
        var saved = JSON.parse(raw);
        if (saved && /^[A-Za-z0-9._:-]{12,120}$/.test(String(saved.token || '')) && (!authDate || String(saved.auth_date || '') === authDate)) return String(saved.token);
      }
      var token = '';
      if (window.crypto && typeof window.crypto.randomUUID === 'function') token = window.crypto.randomUUID();
      if (!token && window.crypto && typeof window.crypto.getRandomValues === 'function') {
        var bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        token = Array.prototype.map.call(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
      }
      if (!token) token = 'v-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14);
      sessionStorage.setItem(key, JSON.stringify({ token: token, auth_date: authDate }));
      return token;
    } catch (error) {
      return '';
    }
  }

  async function syncEitaaAudience(widget) {
    if (!eitaaWebApp || !eitaaWebApp.initData || !widget) return;
    try {
      var firstCard = currentCard(widget);
      var state = await eitaaPost('eitaa/session', { init_data: eitaaWebApp.initData, card_post_id: firstCard ? Number(firstCard.dataset.id || 0) : 0, visit_token: eitaaVisitToken() });
      if (state.eitaa_authenticated) {
        widget.dataset.eitaaAuthenticated = 'true';
        if (firstCard) firstCard.dataset.favorite = state.is_favorite ? 'true' : 'false';
        updateFavoriteButton(widget);
      }
      widget.dataset.dailyEnabled = state.daily_enabled ? 'true' : 'false';
      widget.dataset.dailyAllowsWrite = state.allows_write_to_pm ? 'true' : 'false';
      widget.dataset.dailyOptedOut = state.daily_opted_out ? 'true' : 'false';
      widget.dataset.dailySendTime = String(state.daily_send_time || '08:00');
      var settingsOpen = widget.querySelector('[data-dhikra-daily-settings-open]');
      if (settingsOpen) settingsOpen.hidden = false;
      updateDailyShortLabel(widget);
      if (state.reward && state.reward.is_winner && state.reward.winner_message) {
        showReward(widget, state.reward);
        requestWinnerWriteAccess(widget, state.reward);
      }
      if (state.reward && state.reward.card_html && widget.dataset.hasRequestedNext !== 'true') {
        var slot = widget.querySelector('[data-dhikra-card-slot]');
        if (slot) { slot.innerHTML = state.reward.card_html; var syncedCard = currentCard(widget); if (syncedCard) writeRecent(syncedCard.dataset.code); fitCard(syncedCard); updateFavoriteButton(widget); cacheCurrentCard(widget); }
      }
      // Daily delivery is the primary Eitaa experience. On the first
      // authenticated launch, request Eitaa's native permission immediately;
      // there is no separate Dhikra activation step.
      autoEnrollDailyOnFirstLaunch(widget, state);
    } catch (error) {
      // Identity/reward failures must never prevent the first random card from being read.
      if (window.console && console.warn) console.warn('Dhikra Eitaa session:', error.message);
    }

    bindDailySettings(widget);
  }

  function bindDailySettings(widget) {
    var open = widget.querySelector('[data-dhikra-daily-settings-open]');
    var panel = widget.querySelector('[data-dhikra-daily-settings]');
    if (!open || !panel || panel.dataset.dhikraBound) return;
    panel.dataset.dhikraBound = '1';
    open.addEventListener('click', function () {
      var enabled = panel.querySelector('[data-dhikra-daily-enabled]');
      if (enabled) enabled.checked = widget.dataset.dailyEnabled === 'true';
      panel.hidden = false; document.body.classList.add('dhikra-has-dialog');
      if (enabled) enabled.focus();
    });
    panel.addEventListener('click', function (event) {
      if (event.target.closest('[data-dhikra-daily-settings-close]')) { panel.hidden = true; document.body.classList.remove('dhikra-has-dialog'); return; }
      if (!event.target.closest('[data-dhikra-daily-save]')) return;
      var enabled = Boolean(panel.querySelector('[data-dhikra-daily-enabled]') && panel.querySelector('[data-dhikra-daily-enabled]').checked);
      if (enabled) {
        requestEitaaMessagePermission(widget, function (granted, newlyGranted) {
          if (granted) saveDailyPreference(widget, true, newlyGranted, panel);
        });
      } else {
        saveDailyPreference(widget, false, false, panel);
      }
    });
  }

  function normalizeSavedText(value) {
    return String(value || '').toLocaleLowerCase('fa').replace(/\s+/g, ' ').trim();
  }

  function savedApi(root, item, favorite) {
    if (root.dataset.dhikraSavedMode === 'eitaa') {
      return eitaaPost('eitaa/favorites/' + encodeURIComponent(item.code), { init_data: eitaaWebApp.initData, favorite: favorite });
    }
    return fetch(config.restUrl + 'favorites/' + encodeURIComponent(item.code), {
      method: 'POST', credentials: 'same-origin', headers: restHeaders(), body: JSON.stringify({ favorite: favorite })
    }).then(function (response) { return apiData(response, 'favorites/' + item.code); });
  }

  function createSavedCard(item, root) {
    var article = document.createElement('article'); article.className = 'dhikra-saved-card'; article.dataset.dhikraSavedItem = '1'; article.dataset.code = item.code || ''; article.dataset.title = item.title || ''; article.dataset.topic = item.topic || ''; article.dataset.savedTs = String(item.saved_ts || 0);
    var top = document.createElement('div'); top.className = 'dhikra-saved-card__top';
    var chips = document.createElement('div'); chips.className = 'dhikra-saved-card__chips';
    if (item.topic) { var topic = document.createElement('span'); topic.textContent = item.topic; chips.appendChild(topic); }
    if (item.type_label) { var type = document.createElement('span'); type.className = 'is-type'; type.textContent = item.type_label; chips.appendChild(type); }
    var heart = document.createElement('span'); heart.className = 'dhikra-saved-card__heart'; heart.setAttribute('aria-hidden', 'true'); heart.textContent = '♥'; top.appendChild(chips); top.appendChild(heart);
    var title = document.createElement('h2'); var titleLink = document.createElement('a'); titleLink.href = item.url || '#'; titleLink.textContent = item.title || ''; title.appendChild(titleLink);
    article.appendChild(top); article.appendChild(title);
    if (item.excerpt) { var excerpt = document.createElement('p'); excerpt.className = 'dhikra-saved-card__excerpt'; excerpt.textContent = item.excerpt; article.appendChild(excerpt); }
    var meta = document.createElement('div'); meta.className = 'dhikra-saved-card__meta';
    if (item.source) { var source = document.createElement('span'); source.textContent = item.source; meta.appendChild(source); }
    if (item.saved_label) { var saved = document.createElement('time'); saved.textContent = item.saved_label; if (item.saved_at) saved.dateTime = item.saved_at; meta.appendChild(saved); }
    if (meta.children.length) article.appendChild(meta);
    var actions = document.createElement('div'); actions.className = 'dhikra-saved-card__actions';
    var view = document.createElement('a'); view.href = item.url || '#'; view.innerHTML = '<span aria-hidden="true">↗</span><span>باز کردن</span>';
    var remove = document.createElement('button'); remove.type = 'button'; remove.dataset.dhikraSavedRemove = '1'; remove.innerHTML = '<span aria-hidden="true">×</span><span>حذف</span>';
    remove.addEventListener('click', function () { removeSavedItem(root, item, remove); });
    actions.appendChild(view); actions.appendChild(remove); article.appendChild(actions);
    return article;
  }

  function refreshSavedFilters(root) {
    var state = root._dhikraSavedState; if (!state) return;
    var wrap = root.querySelector('[data-dhikra-saved-filters]'); if (!wrap) return;
    var topics = [];
    state.items.forEach(function (item) { if (item.topic && topics.indexOf(item.topic) < 0) topics.push(item.topic); });
    topics.sort(function (a, b) { return a.localeCompare(b, 'fa'); });
    wrap.replaceChildren();
    [''].concat(topics).forEach(function (topic) {
      var button = document.createElement('button'); button.type = 'button'; button.dataset.topic = topic; button.textContent = topic || 'همه'; button.classList.toggle('is-active', state.topic === topic);
      button.addEventListener('click', function () { state.topic = topic; refreshSavedFilters(root); renderSavedCollection(root); });
      wrap.appendChild(button);
    });
  }

  function renderSavedCollection(root) {
    var state = root._dhikraSavedState; if (!state) return;
    var list = root.querySelector('[data-dhikra-saved-list]'); var count = root.querySelector('[data-dhikra-saved-count]'); var status = root.querySelector('[data-dhikra-saved-status]'); var empty = root.querySelector('[data-dhikra-saved-empty]'); var noResults = root.querySelector('[data-dhikra-saved-no-results]');
    if (!list) return;
    var query = normalizeSavedText(state.query); var filtered = state.items.filter(function (item) {
      if (state.topic && item.topic !== state.topic) return false;
      if (!query) return true;
      return normalizeSavedText([item.title, item.topic, item.type_label, item.excerpt, item.source].join(' ')).indexOf(query) >= 0;
    });
    filtered.sort(function (a, b) { var delta = Number(b.saved_ts || 0) - Number(a.saved_ts || 0); return state.sort === 'oldest' ? -delta : delta; });
    list.replaceChildren(); filtered.forEach(function (item) { list.appendChild(createSavedCard(item, root)); });
    if (count) count.textContent = String(state.total);
    if (status) status.textContent = state.total ? (filtered.length === state.total ? 'تلنگر ذخیره‌شده' : 'از ' + String(state.total) + ' ذخیره‌شده، ' + String(filtered.length) + ' مورد نمایش داده می‌شود') : 'تلنگر ذخیره‌شده';
    if (empty) empty.hidden = state.total !== 0;
    if (noResults) noResults.hidden = state.total === 0 || filtered.length !== 0;
  }

  function showSavedUndo(root, item, index) {
    var state = root._dhikraSavedState; var bar = root.querySelector('[data-dhikra-saved-undo]'); var button = root.querySelector('[data-dhikra-saved-undo-button]');
    if (!state || !bar || !button) return;
    if (state.undoTimer) clearTimeout(state.undoTimer);
    state.undo = { item: item, index: index }; bar.hidden = false;
    state.undoTimer = setTimeout(function () { bar.hidden = true; state.undo = null; }, 6500);
    if (button.dataset.dhikraBound) return;
    button.dataset.dhikraBound = '1';
    button.addEventListener('click', async function () {
      if (!state.undo) return;
      var pending = state.undo; button.disabled = true;
      try {
        await savedApi(root, pending.item, true);
        pending.item.saved_ts = Math.floor(Date.now() / 1000); pending.item.saved_at = new Date().toISOString(); pending.item.saved_label = 'همین حالا';
        state.items.splice(Math.min(pending.index, state.items.length), 0, pending.item); state.total += 1; state.undo = null; bar.hidden = true; refreshSavedFilters(root); renderSavedCollection(root);
      } catch (error) {
        var status = root.querySelector('[data-dhikra-saved-status]'); if (status) status.textContent = error.message || 'برگرداندن ذخیره انجام نشد.';
      } finally { button.disabled = false; }
    });
  }

  async function removeSavedItem(root, item, button) {
    var state = root._dhikraSavedState; if (!state || button.disabled) return;
    button.disabled = true;
    try {
      await savedApi(root, item, false);
      var index = state.items.findIndex(function (candidate) { return candidate.code === item.code; });
      if (index >= 0) state.items.splice(index, 1);
      state.total = Math.max(0, state.total - 1); refreshSavedFilters(root); renderSavedCollection(root); showSavedUndo(root, item, Math.max(0, index));
    } catch (error) {
      button.disabled = false; var status = root.querySelector('[data-dhikra-saved-status]'); if (status) status.textContent = error.message || 'حذف از ذخیره‌شده‌ها انجام نشد.';
    }
  }

  async function setupSavedCollectionPage() {
    var root = document.querySelector('[data-dhikra-saved-page]'); if (!root) return;
    var search = root.querySelector('[data-dhikra-saved-search]'); var sort = root.querySelector('[data-dhikra-saved-sort]'); var status = root.querySelector('[data-dhikra-saved-status]'); var login = root.querySelector('[data-dhikra-web-login]'); var pagination = root.querySelector('[data-dhikra-saved-pagination]');
    root._dhikraSavedState = { items: [], total: 0, topic: '', query: '', sort: 'newest', undo: null, undoTimer: null };
    if (search) search.addEventListener('input', function () { root._dhikraSavedState.query = search.value; renderSavedCollection(root); });
    if (sort) sort.addEventListener('change', function () { root._dhikraSavedState.sort = sort.value; renderSavedCollection(root); });
    var mode = root.dataset.dhikraSavedMode;
    if (mode === 'eitaa' && (!eitaaWebApp || !eitaaWebApp.initData)) {
      if (status) status.textContent = 'برای مشاهده ذخیره‌شده‌ها وارد نسخه وب شوید.'; if (login) login.hidden = false; return;
    }
    try {
      var data;
      if (mode === 'eitaa') data = await eitaaPost('eitaa/favorites', { init_data: eitaaWebApp.initData });
      else { var response = await fetch(config.restUrl + 'favorites', { credentials: 'same-origin', headers: restHeaders() }); data = await apiData(response, 'favorites'); }
      root._dhikraSavedState.items = data.items || []; root._dhikraSavedState.total = Number(data.total || root._dhikraSavedState.items.length); refreshSavedFilters(root); renderSavedCollection(root); if (pagination) pagination.hidden = true;
    } catch (error) {
      if (mode === 'web') {
        /* Keep the server-rendered fallback intact if REST enhancement fails. */
        if (status) status.textContent = 'ذخیره‌شده‌ها نمایش داده شده‌اند؛ جستجوی پیشرفته موقتاً در دسترس نیست.';
      } else if (status) status.textContent = 'دریافت ذخیره‌شده‌ها انجام نشد.';
    }
  }

  setupSavedCollectionPage();

  function showAnonymousWeb(root) {
    var status = root && root.querySelector('[data-dhikra-personal-status]');
    var login = root && root.querySelector('[data-dhikra-web-login]');
    if (status) status.textContent = 'برای ادامه وارد نسخه وب شوید.';
    if (login) login.hidden = false;
  }

  function topicFields(topics, selected, name) {
    var wrap = document.createElement('div'); wrap.className = 'dhikra-topic-grid';
    (topics || []).forEach(function (topic) {
      var label = document.createElement('label'); var input = document.createElement('input'); var span = document.createElement('span');
      input.type = 'checkbox'; input.name = name; input.value = String(topic.id); input.checked = (selected || []).map(Number).indexOf(Number(topic.id)) >= 0;
      span.textContent = topic.name; label.appendChild(input); label.appendChild(span); wrap.appendChild(label);
    });
    return wrap;
  }

  async function setupPersonalPage() {
    var root = document.querySelector('[data-dhikra-personal-page]');
    var nav = document.querySelector('[data-dhikra-eitaa-nav]');
    var anonymousLogin = document.querySelector('[data-dhikra-anonymous-login]');
    var authPanel = document.querySelector('.dhikra-mobile-auth');
    if (!eitaaWebApp || !eitaaWebApp.initData) {
      if (anonymousLogin) anonymousLogin.hidden = false;
      if (root) showAnonymousWeb(root);
      return;
    }
    if (nav) nav.hidden = false;
    if (anonymousLogin) anonymousLogin.hidden = true;
    try {
      var account = await eitaaPost('eitaa/account', { init_data: eitaaWebApp.initData });
      if (authPanel) {
        authPanel.replaceChildren(); var signed = document.createElement('p'); signed.textContent = 'شما از طریق ایتا وارد ذِكْرَىٰ شده‌اید.';
        var back = document.createElement('a'); back.className = 'dhikra-submit'; back.href = config.homeUrl; back.textContent = 'بازگشت به تلنگرها'; authPanel.appendChild(signed); authPanel.appendChild(back);
      }
      if (!root) return;
      var page = root.dataset.dhikraPersonalPage; var status = root.querySelector('[data-dhikra-personal-status]'); var content = root.querySelector('[data-dhikra-personal-content]');
      if (status) status.textContent = '';
      if (page === 'account') {
        var title = document.createElement('h1'); title.textContent = account.display_name || 'حساب ایتا';
        var note = document.createElement('p'); note.textContent = 'ورود از طریق ایتا';
        var stats = document.createElement('p'); stats.textContent = String(account.favorite_count) + ' ذخیره‌شده — ' + String(account.history_count) + ' تلنگر اخیر';
        var heading = document.createElement('h2'); heading.textContent = 'موضوعات دلخواه';
        var fields = topicFields(account.active_topics, account.selected_topic_ids, 'topics');
        var save = document.createElement('button'); save.type = 'button'; save.className = 'dhikra-submit'; save.textContent = 'ذخیرهٔ موضوعات';
        save.addEventListener('click', async function () { var ids = Array.prototype.slice.call(fields.querySelectorAll('input:checked')).map(function (x) { return Number(x.value); }); try { await eitaaPost('eitaa/preferences', { init_data: eitaaWebApp.initData, selected_topic_ids: ids }); status.textContent = 'موضوعات ذخیره شد.'; } catch (e) { status.textContent = e.message; } });
        content.appendChild(title); content.appendChild(note); content.appendChild(stats); content.appendChild(heading); content.appendChild(fields); content.appendChild(save);
      } else if (page === 'history') {
        var history = await eitaaPost('eitaa/history', { init_data: eitaaWebApp.initData, page: 1 });
        var h = document.createElement('h1'); h.textContent = 'تلنگرهای اخیر'; var list = document.createElement('ol'); list.className = 'dhikra-history-list';
        (history.items || []).forEach(function (item) { var li=document.createElement('li'), a=document.createElement('a'); a.href=item.url; a.textContent=item.title; li.appendChild(a); list.appendChild(li); });
        content.appendChild(h); content.appendChild(list); if (!history.items.length) { var empty=document.createElement('p'); empty.textContent='تاریخچه‌ای وجود ندارد.'; content.appendChild(empty); }
      } else if (page === 'print') {
        var ph=document.createElement('h1'); ph.textContent='ساخت کارت‌های چاپی'; var fields2=topicFields(account.active_topics, account.selected_topic_ids, 'print_topics');
        var count=document.createElement('input'); count.type='number'; count.min='1'; count.max='500'; count.value='12'; count.required=true;
        var generate=document.createElement('button'); generate.type='button'; generate.className='dhikra-submit'; generate.textContent='ساخت پیش‌نمایش چاپ';
        generate.addEventListener('click',async function(){var ids=Array.prototype.slice.call(fields2.querySelectorAll('input:checked')).map(function(x){return Number(x.value);}); try{var out=await eitaaPost('eitaa/print',{init_data:eitaaWebApp.initData,topic_ids:ids,count:Number(count.value)}); location.href=out.preview_url;}catch(e){status.textContent=e.message;}});
        content.appendChild(ph); content.appendChild(fields2); content.appendChild(count); content.appendChild(generate);
      } else if (page === 'print-preview') {
        var preview=await eitaaPost('eitaa/print-preview',{init_data:eitaaWebApp.initData,token:root.dataset.dhikraPrintToken||''});
        var toolbar=document.createElement('div'); toolbar.className='dhikra-print-toolbar'; var h2=document.createElement('h1'); h2.textContent='کارت‌های آماده چاپ'; var print=document.createElement('button'); print.type='button'; print.className='dhikra-submit'; print.textContent='چاپ / ذخیره PDF'; print.addEventListener('click',function(){window.print();}); toolbar.appendChild(h2); toolbar.appendChild(print);
        var pages=document.createElement('div'); pages.className='dhikra-print-pages'; pages.innerHTML=preview.cards_html; content.appendChild(toolbar); content.appendChild(pages);
      }
    } catch (error) {
      if (root) { var status = root.querySelector('[data-dhikra-personal-status]'); if (status) status.textContent = 'احراز هویت ایتا انجام نشد. لطفاً برنامک را از داخل ایتا دوباره باز کنید.'; }
    }
  }

  setupPersonalPage();

  if (!widgets.length) return;

  function message(widget, text) {
    var live = widget.querySelector('[data-dhikra-live]');
    if (!live) return;
    window.clearTimeout(widget.dhikraMessageTimer);
    live.classList.remove('is-visible');
    live.textContent = text || '';
    if (!text) return;
    window.requestAnimationFrame(function () { live.classList.add('is-visible'); });
    widget.dhikraMessageTimer = window.setTimeout(function () {
      live.classList.remove('is-visible');
      window.setTimeout(function () { if (!live.classList.contains('is-visible')) live.textContent = ''; }, 200);
    }, 2400);
  }

  function confirmAction(button) {
    if (!button) return;
    var icon = button.querySelector('span:first-child');
    var original = icon ? icon.textContent : '';
    button.classList.add('is-confirmed');
    if (icon) icon.textContent = '✓';
    window.setTimeout(function () {
      button.classList.remove('is-confirmed');
      if (icon) icon.textContent = original;
    }, 1150);
  }

  function pulseAction(button) {
    if (!button) return;
    button.classList.remove('is-pulsing');
    void button.offsetWidth;
    button.classList.add('is-pulsing');
    window.setTimeout(function () { button.classList.remove('is-pulsing'); }, 420);
  }

  function currentCard(widget) {
    return widget.querySelector('[data-dhikra-card]');
  }

  var resumeCacheKey = 'dhikra_resume_card_v3';
  function cacheCurrentCard(widget) {
    if (!widget || config.route && config.route !== 'home') return;
    var slot = widget.querySelector('[data-dhikra-card-slot]');
    var card = currentCard(widget);
    if (!slot || !card) return;
    try {
      sessionStorage.setItem(resumeCacheKey, JSON.stringify({
        saved_at: Date.now(),
        html: slot.innerHTML,
        url: card.dataset.url || location.href,
        code: card.dataset.code || ''
      }));
    } catch (error) {}
  }

  function restoreCachedCard(widget) {
    if (!widget || config.route && config.route !== 'home') return false;
    try {
      var raw = sessionStorage.getItem(resumeCacheKey);
      if (!raw) return false;
      var cached = JSON.parse(raw);
      if (!cached || !cached.html || Date.now() - Number(cached.saved_at || 0) > 30 * 60 * 1000) return false;
      var slot = widget.querySelector('[data-dhikra-card-slot]');
      if (!slot) return false;
      slot.innerHTML = cached.html;
      var card = currentCard(widget);
      if (!card) return false;
      fitCard(card);
      updateFavoriteButton(widget);
      if (cached.url) history.replaceState({}, '', cached.url);
      return true;
    } catch (error) { return false; }
  }

  function readRecent() {
    var match = document.cookie.match(/(?:^|; )dhikra_recent=([^;]*)/);
    if (!match) return [];
    try {
      return decodeURIComponent(match[1]).split(',').filter(function (code) {
        return /^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5}$/.test(code);
      });
    } catch (e) {
      return [];
    }
  }

  function writeRecent(code) {
    if (!code) return;
    var limit = Math.max(1, Math.min(30, Number(config.repeatWindow || 12)));
    var recent = readRecent().filter(function (item) { return item !== code; });
    recent.unshift(code);
    recent = recent.slice(0, limit);
    document.cookie = 'dhikra_recent=' + encodeURIComponent(recent.join(',')) + '; Max-Age=2592000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  function fitCard(card) {
    if (!card) return;
    var content = card.querySelector('[data-dhikra-fit-content]');
    if (!content) return;
    card.classList.remove('is-too-long');
    card.style.setProperty('--dhikra-fit', '1');
    card.style.setProperty('--dhikra-gap', '1');

    var fit = 1;
    var gap = 1;
    var iterations = 0;
    while (content.scrollHeight > content.clientHeight + 1 && fit > 0.84 && iterations < 10) {
      fit = Math.max(0.84, fit - 0.02);
      gap = Math.max(0.72, gap - 0.03);
      card.style.setProperty('--dhikra-fit', fit.toFixed(2));
      card.style.setProperty('--dhikra-gap', gap.toFixed(2));
      iterations += 1;
    }
    if (content.scrollHeight > content.clientHeight + 2) {
      card.dataset.fitWarning = (config.messages && config.messages.fitError) || '';
      card.classList.add('is-too-long');
    }
  }

  function updateFavoriteButton(widget) {
    var card = currentCard(widget);
    var button = widget.querySelector('[data-dhikra-favorite]');
    if (!card || !button) return;
    var pressed = typeof card.dataset.favorite === 'string' ? card.dataset.favorite === 'true' : button.getAttribute('aria-pressed') === 'true';
    button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
    var icon = button.querySelector('span:first-child');
    if (icon) icon.textContent = pressed ? '♥' : '♡';
  }

  function restHeaders() {
    var headers = { 'Content-Type': 'application/json' };
    if (config.nonce) headers['X-WP-Nonce'] = config.nonce;
    return headers;
  }

  async function fetchNext(widget) {
    var slot = widget.querySelector('[data-dhikra-card-slot]');
    var button = widget.querySelector('[data-dhikra-next]');
    if (!slot || !button || button.disabled) return;
    button.disabled = true;
    slot.classList.add('is-loading');
    message(widget, (config.messages && config.messages.loading) || '?');
    try {
      var exclude = readRecent().join(',');
      var data;
      if (eitaaWebApp && eitaaWebApp.initData) {
        data = await eitaaPost('eitaa/random', { init_data: eitaaWebApp.initData, exclude: exclude });
      } else {
        var response = await fetch(config.restUrl + 'telangors/random?platform=general&exclude=' + encodeURIComponent(exclude), {
          credentials: 'same-origin'
        });
        data = await apiData(response, 'telangors/random');
      }
      slot.innerHTML = data.html;
      var card = currentCard(widget);
      if (card) {
        card.dataset.favorite = data.is_favorite ? 'true' : 'false';
        writeRecent(data.code);
        fitCard(card);
        card.classList.add('is-entering');
        card.addEventListener('animationend', function () { card.classList.remove('is-entering'); }, { once: true });
        cacheCurrentCard(widget);
      }
      if (!eitaaWebApp && config.isLoggedIn && card) {
        try {
          var favoritesResponse = await fetch(config.restUrl + 'favorites', { credentials: 'same-origin', headers: restHeaders() });
          var favoritesData = await apiData(favoritesResponse, 'favorites');
          card.dataset.favorite = (favoritesData.items || []).some(function (item) { return item.code === card.dataset.code; }) ? 'true' : 'false';
        } catch (favoriteError) {}
      }
      updateFavoriteButton(widget);
      // The prize draw now happens on a later server-selected card, never on launch.
      if (data.reward && data.reward.is_winner && data.reward.created && data.reward.winner_message) {
        showReward(widget, data.reward);
        requestWinnerWriteAccess(widget, data.reward);
      }
      history.replaceState({}, '', data.url || location.href);
      message(widget, '');
      // First-run prompting happens after Eitaa session, not after pressing Next.
    } catch (error) {
      message(widget, (config.messages && config.messages.error) || 'دریافت تلنگر تازه انجام نشد.');
    } finally {
      slot.classList.remove('is-loading');
      button.disabled = false;
    }
  }

  async function toggleFavorite(widget) {
    var card = currentCard(widget);
    var button = widget.querySelector('[data-dhikra-favorite]');
    if (!card || !button) return;
    var inEitaa = Boolean(eitaaWebApp && eitaaWebApp.initData);
    if (!inEitaa && !config.isLoggedIn) {
      var returnTo = card.dataset.url || location.href;
      var separator = String(config.loginUrl || '').indexOf('?') >= 0 ? '&' : '?';
      location.href = String(config.loginUrl || '/') + separator + 'return_to=' + encodeURIComponent(returnTo);
      return;
    }
    var nextState = button.getAttribute('aria-pressed') !== 'true';
    button.disabled = true;
    try {
      var data;
      if (inEitaa) {
        data = await eitaaPost('eitaa/favorites/' + encodeURIComponent(card.dataset.code), {
          init_data: eitaaWebApp.initData,
          favorite: nextState
        });
      } else {
        var response = await fetch(config.restUrl + 'favorites/' + encodeURIComponent(card.dataset.code), {
          method: 'POST',
          credentials: 'same-origin',
          headers: restHeaders(),
          body: JSON.stringify({ favorite: nextState })
        });
        data = await apiData(response, 'favorites/' + card.dataset.code);
      }
      button.setAttribute('aria-pressed', data.favorite ? 'true' : 'false');
      card.dataset.favorite = data.favorite ? 'true' : 'false';
      updateFavoriteButton(widget);
      cacheCurrentCard(widget);
      pulseAction(button);
      message(widget, data.favorite ? (config.messages && config.messages.favorite) : (config.messages && config.messages.unfavorite));
    } catch (error) {
      message(widget, error.message);
    } finally {
      button.disabled = false;
    }
  }

  function sharePayload(card) {
    var websiteUrl = card.dataset.url || location.href;
    var url = eitaaWebApp ? String(config.eitaaAppUrl || 'https://eitaa.com/talanghor_app') : websiteUrl;
    var text = String(
      (eitaaWebApp && (card.dataset.shareTextEitaaFull || card.dataset.shareTextEitaa)) ||
      card.dataset.shareText ||
      ''
    ).trim();

    if (eitaaWebApp) {
      // Eitaa sharing must never leak the website URL and must always end with
      // the canonical mini-app link. The server-provided full payload already
      // contains every card field; these guards also cover cached old markup.
      if (websiteUrl && websiteUrl !== url) text = text.split(websiteUrl).join(url);
      if (url) {
        var escaped = url.replace(/[.*+?^$(){}|[\]\\]/g, '\\$&');
        text = text.replace(new RegExp('(?:\\s*' + escaped + '){2,}', 'g'), '\n' + url).trim();
      }
    }
    if (url && text.indexOf(url) === -1) text = text ? text + '\n\n' + url : url;

    return { title: document.title, text: text, url: url };
  }

  async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return;
    }
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
  }

  var eitaaShareReturnPending = false;
  var eitaaShareReloadStarted = false;
  var eitaaSharePendingKey = 'dhikra_eitaa_share_pending_v2';
  var eitaaShareReturnUrlKey = 'dhikra_eitaa_share_return_url_v2';

  function markEitaaShareOpened(card) {
    eitaaShareReturnPending = true;
    var returnUrl = card && card.dataset && card.dataset.url ? card.dataset.url : window.location.href;
    try {
      sessionStorage.setItem(eitaaSharePendingKey, '1');
      sessionStorage.setItem(eitaaShareReturnUrlKey, returnUrl);
    } catch (error) {}
  }

  function recoverAfterEitaaShare() {
    if (eitaaShareReloadStarted) return true;
    var pending = eitaaShareReturnPending;
    var returnUrl = '';
    try {
      pending = pending || sessionStorage.getItem(eitaaSharePendingKey) === '1';
      returnUrl = sessionStorage.getItem(eitaaShareReturnUrlKey) || '';
    } catch (error) {}
    if (!pending) return false;

    // Do not try to revive the old Android WebView layer tree. On affected
    // Eitaa clients it can keep only the discovery bar painted while the card
    // and action dock remain blank. Clear the marker first, then rebuild the
    // exact card route from the server in the same WebView.
    eitaaShareReloadStarted = true;
    eitaaShareReturnPending = false;
    try {
      sessionStorage.removeItem(eitaaSharePendingKey);
      sessionStorage.removeItem(eitaaShareReturnUrlKey);
    } catch (error) {}

    var target = returnUrl;
    if (!target) {
      var widget = widgets && widgets.length ? widgets[0] : null;
      var card = widget ? currentCard(widget) : null;
      target = card && card.dataset.url ? card.dataset.url : (config.homeUrl || window.location.href);
    }
    window.setTimeout(function () {
      var reloadTarget = target;
      try {
        var parsedTarget = new URL(target, window.location.href);
        parsedTarget.searchParams.set('dhikra_resume', '1');
        reloadTarget = parsedTarget.toString();
      } catch (error) {}
      try { window.location.replace(reloadTarget); }
      catch (error) { window.location.href = reloadTarget; }
    }, 60);
    return true;
  }

  async function shareCard(widget) {
    var card = currentCard(widget);
    if (!card) return;
    var payload = sharePayload(card);
    try {
      // Inside Eitaa prefer the SDK-native route. The official SDK documents
      // that openEitaaLink keeps the mini-app open; navigator.share is only a
      // fallback for normal browsers because some Android WebViews return from
      // the system share sheet with an unpainted (white) surface.
      if (eitaaWebApp && typeof eitaaWebApp.openEitaaLink === 'function') {
        var shareText = String(payload.text || '').trim();
        if (!shareText) throw new Error('متن اشتراک‌گذاری آماده نیست.');
        markEitaaShareOpened(card);
        var shareUrl = 'https://eitaa.com/share/url?url=' + encodeURIComponent(shareText);
        eitaaWebApp.openEitaaLink(shareUrl);
        confirmAction(widget.querySelector('[data-dhikra-share]'));
        message(widget, (config.messages && config.messages.shared) || 'اشتراک‌گذاری باز شد.');
        return;
      }
      if (navigator.share) {
        await navigator.share({ title: payload.title, text: payload.text });
        confirmAction(widget.querySelector('[data-dhikra-share]'));
        message(widget, (config.messages && config.messages.shared) || 'اشتراک‌گذاری باز شد.');
        return;
      }
      await copyText(payload.text);
      confirmAction(widget.querySelector('[data-dhikra-share]'));
      message(widget, (config.messages && config.messages.copied) || 'متن کارت کپی شد.');
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (window.console && console.error) console.error('Dhikra share error', error);
      message(widget, error && error.message ? error.message : 'اشتراک‌گذاری انجام نشد.');
    }
  }

  async function copyCard(widget) {
    var card = currentCard(widget);
    if (!card) return;
    var payload = sharePayload(card);
    await copyText(payload.text);
    confirmAction(widget.querySelector('[data-dhikra-copy]'));
    message(widget, (config.messages && config.messages.copied) || '');
  }


  function toolsDialog(widget) {
    return widget ? widget.querySelector('[data-dhikra-tools-dialog]') : null;
  }

  function showToolsPane(dialog, paneName) {
    if (!dialog) return;
    var menu = dialog.querySelector('[data-dhikra-tools-menu]');
    var note = dialog.querySelector('[data-dhikra-note-pane]');
    var reminder = dialog.querySelector('[data-dhikra-reminder-pane]');
    if (menu) menu.hidden = paneName !== 'menu';
    if (note) note.hidden = paneName !== 'note';
    if (reminder) reminder.hidden = paneName !== 'reminder';
  }


  function closeTools(widget) {
    var dialog = toolsDialog(widget);
    if (!dialog) return;
    dialog.hidden = true;
    document.body.classList.remove('dhikra-has-dialog');
    showToolsPane(dialog, 'menu');
  }

  async function loadCardNote(widget) {
    var card = currentCard(widget);
    var dialog = toolsDialog(widget);
    var input = dialog && dialog.querySelector('[data-dhikra-note-input]');
    if (!card || !dialog || !input) return;
    input.disabled = true;
    input.value = '';
    try {
      var data;
      if (eitaaWebApp && eitaaWebApp.initData) {
        data = await eitaaPost('eitaa/note/' + encodeURIComponent(card.dataset.code), { init_data: eitaaWebApp.initData, action: 'get' });
      } else if (config.isLoggedIn) {
        var response = await fetch(config.restUrl + 'notes/' + encodeURIComponent(card.dataset.code), { credentials: 'same-origin', headers: restHeaders() });
        data = await apiData(response, 'notes/' + card.dataset.code);
      } else {
        message(widget, 'برای ثبت یادداشت شخصی وارد حساب شوید.');
        closeTools(widget);
        return;
      }
      input.value = String(data.note || '');
    } catch (error) {
      message(widget, error.message || 'دریافت یادداشت انجام نشد.');
    } finally {
      input.disabled = false;
      if (!input.disabled) input.focus();
    }
  }

  async function saveCardNote(widget, clearNote) {
    var card = currentCard(widget);
    var dialog = toolsDialog(widget);
    var input = dialog && dialog.querySelector('[data-dhikra-note-input]');
    if (!card || !dialog || !input) return;
    var note = clearNote ? '' : String(input.value || '').trim();
    var buttons = dialog.querySelectorAll('[data-dhikra-note-save], [data-dhikra-note-clear]');
    Array.prototype.forEach.call(buttons, function (button) { button.disabled = true; });
    try {
      var data;
      if (eitaaWebApp && eitaaWebApp.initData) {
        data = await eitaaPost('eitaa/note/' + encodeURIComponent(card.dataset.code), { init_data: eitaaWebApp.initData, action: 'save', note: note });
      } else if (config.isLoggedIn) {
        var response = await fetch(config.restUrl + 'notes/' + encodeURIComponent(card.dataset.code), {
          method: 'POST', credentials: 'same-origin', headers: restHeaders(), body: JSON.stringify({ note: note })
        });
        data = await apiData(response, 'notes/' + card.dataset.code);
      } else {
        throw new Error('برای ثبت یادداشت شخصی وارد حساب شوید.');
      }
      input.value = String(data.note || '');
      message(widget, note ? 'یادداشت شخصی ذخیره شد.' : 'یادداشت پاک شد.');
      closeTools(widget);
    } catch (error) {
      message(widget, error.message || 'ذخیره یادداشت انجام نشد.');
    } finally {
      Array.prototype.forEach.call(buttons, function (button) { button.disabled = false; });
    }
  }

  function ensureReminderPermission(widget, callback) {
    if (!eitaaWebApp || !eitaaWebApp.initData) {
      message(widget, 'یادآوری دوباره در برنامک ایتا در دسترس است.');
      return;
    }
    requestEitaaMessagePermission(widget, async function (granted, newlyGranted) {
      if (!granted) return;
      try {
        if (newlyGranted) {
          var state = await eitaaPost('eitaa/write-access', { init_data: eitaaWebApp.initData, granted: true });
          widget.dataset.dailyAllowsWrite = state.allows_write_to_pm ? 'true' : widget.dataset.dailyAllowsWrite;
          widget.dataset.dailyEnabled = state.daily_enabled ? 'true' : widget.dataset.dailyEnabled;
          updateDailyShortLabel(widget);
        }
        callback();
      } catch (error) {
        message(widget, error.message || 'ثبت مجوز پیام انجام نشد.');
      }
    });
  }

  async function scheduleReminder(widget, preset, button) {
    var card = currentCard(widget);
    if (!card) return;
    ensureReminderPermission(widget, async function () {
      if (button) button.disabled = true;
      try {
        var data = await eitaaPost('eitaa/reminder/' + encodeURIComponent(card.dataset.code), {
          init_data: eitaaWebApp.initData,
          preset: preset
        });
        message(widget, data.scheduled ? 'یادآوری برای «' + String(data.label || '') + '» تنظیم شد.' : 'یادآوری ثبت نشد.');
        closeTools(widget);
      } catch (error) {
        message(widget, error.message || 'تنظیم یادآوری انجام نشد.');
      } finally {
        if (button) button.disabled = false;
      }
    });
  }


  function bindTools(widget) {
    var open = widget.querySelector('[data-dhikra-tools-open]');
    var dialog = toolsDialog(widget);
    if (!open || !dialog || dialog.dataset.dhikraBound) return;
    dialog.dataset.dhikraBound = '1';
    open.addEventListener('click', function () {
      showToolsPane(dialog, 'menu');
      dialog.hidden = false;
      document.body.classList.add('dhikra-has-dialog');
      var first = dialog.querySelector('[data-dhikra-note-open]'); if (first) first.focus();
    });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog || event.target.closest('[data-dhikra-tools-close]')) { closeTools(widget); return; }
      if (event.target.closest('[data-dhikra-tools-back]')) { showToolsPane(dialog, 'menu'); return; }
      if (event.target.closest('[data-dhikra-note-open]')) { showToolsPane(dialog, 'note'); loadCardNote(widget); return; }
      if (event.target.closest('[data-dhikra-reminder-open]')) {
        if (!eitaaWebApp || !eitaaWebApp.initData) { message(widget, 'یادآوری دوباره در برنامک ایتا در دسترس است.'); return; }
        showToolsPane(dialog, 'reminder'); return;
      }
      var save = event.target.closest('[data-dhikra-note-save]'); if (save) { saveCardNote(widget, false); return; }
      var clear = event.target.closest('[data-dhikra-note-clear]'); if (clear) { saveCardNote(widget, true); return; }
      var preset = event.target.closest('[data-dhikra-reminder-preset]'); if (preset) { scheduleReminder(widget, preset.dataset.dhikraReminderPreset, preset); return; }
    });
  }

  async function publicGet(path) {
    var response = await fetch(config.restUrl + path, { credentials: 'same-origin' });
    return apiData(response, path);
  }

  function closeDiscovery(panel) {
    if (!panel) return;
    panel.hidden = true;
    document.body.classList.remove('dhikra-has-dialog');
  }

  function renderDiscoveryResults(widget, panel, items, status, label) {
    var results = panel.querySelector('[data-dhikra-search-results], [data-dhikra-topics-results]');
    if (!results) return;
    results.replaceChildren();
    if (status) status.textContent = items.length ? String(items.length) + ' نتیجه' : 'موردی پیدا نشد.';
    items.forEach(function (item) {
      var button = document.createElement('button');
      button.type = 'button'; button.className = 'dhikra-discovery-result'; button.dataset.code = item.code || '';
      var text = document.createElement('span'); var strong = document.createElement('strong'); var small = document.createElement('small');
      strong.textContent = item.title || 'تلنگر';
      small.textContent = [item.main_topic_name || '', item.type_label || ''].filter(Boolean).join(' · ');
      text.appendChild(strong); text.appendChild(small);
      var arrow = document.createElement('span'); arrow.setAttribute('aria-hidden','true'); arrow.textContent = '←';
      button.appendChild(text); button.appendChild(arrow);
      button.addEventListener('click', function () { loadDiscoveredCard(widget, item.code, panel); });
      results.appendChild(button);
    });
    if (label && status && items.length) status.textContent = label + ' — ' + status.textContent;
  }

  async function loadDiscoveredCard(widget, code, panel) {
    if (!code) return;
    var slot = widget.querySelector('[data-dhikra-card-slot]');
    if (!slot) return;
    slot.classList.add('is-loading');
    try {
      var data;
      if (eitaaWebApp && eitaaWebApp.initData) {
        data = await eitaaPost('eitaa/card/' + encodeURIComponent(code), { init_data: eitaaWebApp.initData });
      } else {
        data = await publicGet('telangors/' + encodeURIComponent(code) + '?platform=general');
      }
      slot.innerHTML = data.html || '';
      var card = currentCard(widget);
      if (card) {
        card.dataset.favorite = data.is_favorite ? 'true' : 'false';
        writeRecent(data.code || code);
        fitCard(card);
        updateFavoriteButton(widget);
        cacheCurrentCard(widget);
        card.classList.add('is-entering');
        card.addEventListener('animationend', function () { card.classList.remove('is-entering'); }, { once: true });
      }
      if (data.url) history.replaceState({}, '', data.url);
      closeDiscovery(panel);
      message(widget, 'تلنگر انتخاب‌شده نمایش داده شد.');
    } catch (error) {
      var status = panel && panel.querySelector('[data-dhikra-search-status], [data-dhikra-topics-status]');
      if (status) status.textContent = error.message || 'دریافت تلنگر انجام نشد.';
    } finally {
      slot.classList.remove('is-loading');
    }
  }

  function setupDiscovery(widget) {
    var searchOpen = widget.querySelector('[data-dhikra-search-open]');
    var topicsOpen = widget.querySelector('[data-dhikra-topics-open]');
    var searchPanel = widget.querySelector('[data-dhikra-search-panel]');
    var topicsPanel = widget.querySelector('[data-dhikra-topics-panel]');
    [searchPanel, topicsPanel].forEach(function (panel) {
      if (!panel) return;
      panel.addEventListener('click', function (event) {
        if (event.target === panel || event.target.closest('[data-dhikra-discovery-close]')) closeDiscovery(panel);
      });
    });
    if (searchOpen && searchPanel) {
      searchOpen.addEventListener('click', function () {
        searchPanel.hidden = false; document.body.classList.add('dhikra-has-dialog');
        var input = searchPanel.querySelector('[data-dhikra-search-input]'); if (input) window.setTimeout(function(){ input.focus(); }, 30);
      });
      var form = searchPanel.querySelector('[data-dhikra-search-form]');
      if (form) form.addEventListener('submit', async function (event) {
        event.preventDefault();
        var input = form.querySelector('[data-dhikra-search-input]'); var status = searchPanel.querySelector('[data-dhikra-search-status]');
        var query = input ? input.value.trim() : '';
        if (query.length < 2) { if (status) status.textContent = 'حداقل دو حرف وارد کنید.'; return; }
        if (status) status.textContent = 'در حال جستجو…';
        try {
          var out = await publicGet('telangors?platform=' + (eitaaWebApp ? 'eitaa' : 'general') + '&per_page=30&search=' + encodeURIComponent(query));
          renderDiscoveryResults(widget, searchPanel, out.items || [], status, 'جستجو: «' + query + '»');
        } catch (error) { if (status) status.textContent = error.message || 'جستجو انجام نشد.'; }
      });
    }
    if (topicsOpen && topicsPanel) {
      topicsOpen.addEventListener('click', async function () {
        topicsPanel.hidden = false; document.body.classList.add('dhikra-has-dialog');
        var list = topicsPanel.querySelector('[data-dhikra-topics-list]'); var status = topicsPanel.querySelector('[data-dhikra-topics-status]');
        if (!list || list.dataset.loaded === 'true') return;
        if (status) status.textContent = 'در حال دریافت موضوعات…';
        try {
          var data = await publicGet('topics'); list.dataset.loaded = 'true'; list.replaceChildren(); if (status) status.textContent = '';
          (data.items || []).forEach(function (topic) {
            var b = document.createElement('button'); b.type='button'; b.className='dhikra-discovery-topic'; b.textContent=topic.name; b.dataset.slug=topic.slug;
            b.addEventListener('click', async function () {
              Array.prototype.forEach.call(list.querySelectorAll('.is-active'), function(x){x.classList.remove('is-active');}); b.classList.add('is-active');
              if (status) status.textContent='در حال دریافت تلنگرهای «'+topic.name+'»…';
              try {
                var out=await publicGet('telangors?platform='+(eitaaWebApp?'eitaa':'general')+'&per_page=30&topic='+encodeURIComponent(topic.slug));
                renderDiscoveryResults(widget,topicsPanel,out.items||[],status,topic.name);
              } catch(error){ if(status) status.textContent=error.message||'دریافت موضوعات انجام نشد.'; }
            });
            list.appendChild(b);
          });
        } catch (error) { if (status) status.textContent = error.message || 'دریافت موضوعات انجام نشد.'; }
      });
    }
  }

  function bindVerticalCardSwipe(widget) {
    var slot = widget.querySelector('[data-dhikra-card-slot]');
    var indicator = widget.querySelector('[data-dhikra-pull-indicator]');
    if (!slot || slot.dataset.pullBound) return;
    slot.dataset.pullBound = '1';
    var startX = 0, startY = 0, pulling = false, distance = 0, direction = '';

    function scrollContent() {
      return slot.querySelector('[data-dhikra-fit-content]');
    }
    function contentAtTop() {
      var content = scrollContent();
      return !content || content.scrollTop <= 1;
    }
    function contentAtBottom() {
      var content = scrollContent();
      if (!content) return true;
      return content.scrollHeight - content.scrollTop - content.clientHeight <= 2;
    }
    function setIndicator(dir, ready, loading) {
      if (!indicator) return;
      indicator.classList.toggle('is-up', dir === 'up');
      indicator.classList.add('is-visible');
      indicator.classList.toggle('is-ready', Boolean(ready));
      indicator.classList.toggle('is-loading', Boolean(loading));
      var arrow = indicator.querySelector('span');
      var text = indicator.querySelector('strong');
      if (arrow && !loading) arrow.textContent = dir === 'up' ? '↑' : '↓';
      if (text) {
        if (loading) text.textContent = 'در حال آوردن تلنگر تازه…';
        else if (ready) text.textContent = 'رها کنید برای تلنگر جدید';
        else text.textContent = dir === 'up' ? 'برای تلنگر جدید به بالا بکشید' : 'برای تلنگر جدید به پایین بکشید';
      }
    }
    function reset() {
      pulling = false; distance = 0; direction = '';
      slot.classList.remove('is-pulling'); slot.classList.add('is-pull-release'); slot.style.transform = '';
      if (indicator) indicator.classList.remove('is-visible','is-ready','is-loading','is-up');
      window.setTimeout(function(){slot.classList.remove('is-pull-release');},220);
    }

    slot.addEventListener('touchstart', function (event) {
      if (!event.touches || event.touches.length !== 1) return;
      startX = event.touches[0].clientX; startY = event.touches[0].clientY;
      pulling = false; distance = 0; direction = '';
    }, { passive: true });

    slot.addEventListener('touchmove', function (event) {
      if (!event.touches || event.touches.length !== 1) return;
      var dx = event.touches[0].clientX - startX;
      var dy = event.touches[0].clientY - startY;
      if (Math.abs(dy) < 12 || Math.abs(dy) < Math.abs(dx) * 1.12) return;

      var dir = dy > 0 ? 'down' : 'up';
      if (dir === 'down' && !contentAtTop()) return;
      if (dir === 'up' && !contentAtBottom()) return;

      event.preventDefault();
      pulling = true; direction = dir;
      var raw = Math.abs(dy);
      distance = Math.min(88, Math.max(0, raw * .48));
      slot.classList.add('is-pulling');
      slot.style.transform = 'translateY(' + (dir === 'up' ? -distance : distance).toFixed(0) + 'px)';
      setIndicator(dir, raw >= 130, false);
    }, { passive: false });

    slot.addEventListener('touchend', function (event) {
      if (!pulling || !direction) return;
      var changed = event.changedTouches && event.changedTouches[0];
      var dy = changed ? changed.clientY - startY : 0;
      var raw = Math.abs(dy);
      var endedDirection = dy >= 0 ? 'down' : 'up';
      if (raw >= 130 && endedDirection === direction) {
        slot.classList.remove('is-pulling'); slot.classList.add('is-pull-release'); slot.style.transform = '';
        setIndicator(direction, false, true);
        widget.dataset.hasRequestedNext = 'true';
        fetchNext(widget).finally(function(){ reset(); });
      } else reset();
    }, { passive: true });
    slot.addEventListener('touchcancel', reset, { passive: true });
  }


  widgets.forEach(function (widget) {
    restoreCachedCard(widget);
    setupDiscovery(widget);
    bindTools(widget);
    if (eitaaWebApp) syncEitaaAudience(widget);
    var rewardClose = widget.querySelector('[data-dhikra-reward-close]');
    if (rewardClose) rewardClose.addEventListener('click', function () { var box = widget.querySelector('[data-dhikra-reward-result]'); if (box) box.hidden = true; document.body.classList.remove('dhikra-has-dialog'); });
    var rewardShare = widget.querySelector('[data-dhikra-reward-share]');
    if (rewardShare) rewardShare.addEventListener('click', async function () {
      var box = widget.querySelector('[data-dhikra-reward-result]'); var text = box ? String(box.dataset.shareText || '') : '';
      try {
        if (eitaaWebApp && typeof eitaaWebApp.openEitaaLink === 'function') {
          markEitaaShareOpened(currentCard(widget));
          eitaaWebApp.openEitaaLink('https://eitaa.com/share/url?url=' + encodeURIComponent(text));
        } else if (navigator.share) {
          await navigator.share({ title: 'خبر خوب ذکری', text: text });
        } else {
          await copyText(text);
          message(widget, 'خبر برنده‌شدن کپی شد.');
        }
      } catch (error) { if (!error || error.name !== 'AbortError') message(widget, error.message || 'اشتراک‌گذاری انجام نشد.'); }
    });
    var card = currentCard(widget);
    if (card) {
      writeRecent(card.dataset.code);
      window.requestAnimationFrame(function () { fitCard(card); });
      card.dataset.favorite = widget.querySelector('[data-dhikra-favorite]') && widget.querySelector('[data-dhikra-favorite]').getAttribute('aria-pressed') === 'true' ? 'true' : 'false';
      updateFavoriteButton(widget);
      cacheCurrentCard(widget);
    }
    bindVerticalCardSwipe(widget);
    widget.addEventListener('click', function (event) {
      var next = event.target.closest('[data-dhikra-next]');
      var favorite = event.target.closest('[data-dhikra-favorite]');
      var share = event.target.closest('[data-dhikra-share]');
      var copy = event.target.closest('[data-dhikra-copy]');
      if (next) { event.preventDefault(); widget.dataset.hasRequestedNext = 'true'; fetchNext(widget); }
      if (favorite) { event.preventDefault(); toggleFavorite(widget); }
      if (share) { event.preventDefault(); shareCard(widget); }
      if (copy) { event.preventDefault(); copyCard(widget).then(function(){ closeTools(widget); }); }
    });
  });

  function refreshVisibleCardLayout() {
    widgets.forEach(function (widget) { var card = currentCard(widget); if (card) fitCard(card); });
  }
  function resumeVisibleApp() {
    if (recoverAfterEitaaShare()) return;
    refreshVisibleCardLayout();
  }
  window.addEventListener('pageshow', resumeVisibleApp, { passive: true });
  window.addEventListener('dhikra:eitaa-activated', resumeVisibleApp, { passive: true });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) resumeVisibleApp(); });

  var resizeTimer = null;
  window.addEventListener('resize', function () {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(function () {
      widgets.forEach(function (widget) { fitCard(currentCard(widget)); });
    }, 120);
  }, { passive: true });
})();

(function () {
  'use strict';

  var selectAll = document.querySelector('[data-dhikra-select-all]');
  var topicWrap = document.querySelector('[data-dhikra-print-topics]');
  if (selectAll && topicWrap) {
    var topicBoxes = Array.prototype.slice.call(topicWrap.querySelectorAll('input[type="checkbox"]'));
    selectAll.addEventListener('change', function () {
      topicBoxes.forEach(function (box) { box.checked = selectAll.checked; });
    });
    topicBoxes.forEach(function (box) {
      box.addEventListener('change', function () {
        var checked = topicBoxes.filter(function (item) { return item.checked; }).length;
        selectAll.checked = checked > 0 && checked === topicBoxes.length;
        selectAll.indeterminate = checked > 0 && checked < topicBoxes.length;
      });
    });
  }

  var printButton = document.querySelector('[data-dhikra-print-now]');
  if (printButton) {
    printButton.addEventListener('click', function () {
      window.print();
    });
  }
})();
