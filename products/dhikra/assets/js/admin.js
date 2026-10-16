(function () {
  'use strict';

  var labels = window.DhikraAdmin || {};

  function updateContentFields(editor) {
    var type = editor.querySelector('#dhikra_type');
    if (!type) return;
    var current = type.value;
    editor.querySelectorAll('[data-dhikra-field="translation"], [data-dhikra-field="reflection"]').forEach(function (field) {
      field.style.display = current === 'story' ? 'none' : '';
    });
    editor.querySelectorAll('[data-dhikra-field="speaker"]').forEach(function (field) {
      field.style.display = current === 'quran' || current === 'story' ? 'none' : '';
    });
    var label = editor.querySelector('[data-dhikra-original-label]');
    if (label) label.textContent = current === 'story' ? (labels.storyText || '') : (labels.mainText || '');
	var original = editor.querySelector('#dhikra_original_text');
	if (original) original.dataset.script = ['quran', 'hadith', 'prayer'].indexOf(current) >= 0 ? 'arabic' : 'persian';
  }

  document.querySelectorAll('[data-dhikra-content-editor]').forEach(function (editor) {
    updateContentFields(editor);
    var type = editor.querySelector('#dhikra_type');
    if (type) type.addEventListener('change', function () { updateContentFields(editor); });
  });
  var general = document.querySelector('#dhikra_share_general');
  var preview = document.querySelector('[data-dhikra-share-preview]');
  if (general && preview) {
    var sample = {};
    try { sample = JSON.parse(preview.dataset.sample || '{}'); } catch (e) { sample = {}; }
    var render = function () {
      var text = general.value;
      var replacements = {
        '[title]': sample.title || '',
        '[arabic]': sample.original || '',
        '[original]': sample.original || '',
        '[translation]': sample.translation || '',
        '[reflection]': sample.reflection || '',
        '[speaker]': sample.speaker || '',
        '[source]': sample.source || '',
        '[topic]': sample.main_topic_name || '',
        '[short_url]': sample.url || '',
        '[site_name]': document.title || ''
      };
      Object.keys(replacements).forEach(function (key) { text = text.split(key).join(replacements[key]); });
      preview.textContent = text.replace(/\n{3,}/g, '\n\n').trim();
    };
    general.addEventListener('input', render);
    render();
  }

  // Only the selected coupon-entry method is shown; servers still validate both paths.
  document.querySelectorAll('.dhikra-coupon-form').forEach(function (form) {
    function syncCouponMode() {
      var selected = form.querySelector('input[name="coupon_mode"]:checked');
      var mode = selected ? selected.value : 'single_use';
      form.querySelectorAll('[data-dhikra-coupon-mode]').forEach(function (section) {
        var active = section.dataset.dhikraCouponMode === mode;
        section.hidden = !active;
        section.querySelectorAll('input,textarea').forEach(function (input) {
          input.disabled = !active;
          if (input.name === 'shared_code' || input.name === 'coupon_codes') input.required = active;
        });
      });
    }
    form.querySelectorAll('input[name="coupon_mode"]').forEach(function (radio) {
      radio.addEventListener('change', syncCouponMode);
    });
    syncCouponMode();
  });

  document.querySelectorAll('.dhikra-broadcast-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.dataset.confirm || '')) { event.preventDefault(); return; }
      var button = form.querySelector('.dhikra-broadcast-submit');
      if (button) { button.disabled = true; button.textContent = 'در حال ثبت…'; }
    });
  });
})();
