(function (blocks, element, blockEditor, i18n) {
  'use strict';
  if (!blocks || !element || !i18n) return;
  const el = element.createElement;
  const __ = i18n.__;
  blocks.registerBlockType('dhikra/telangor', {
    edit: function () {
      return el('div', { className: 'dhikra-block-placeholder' },
        el('strong', null, __('ذِكْرَىٰ', 'dhikra')),
        el('p', null, __('یک تلنگر تصادفی در نمای عمومی نمایش داده می‌شود.', 'dhikra'))
      );
    },
    save: function () { return null; }
  });
})(window.wp && window.wp.blocks, window.wp && window.wp.element, window.wp && window.wp.blockEditor, window.wp && window.wp.i18n);
