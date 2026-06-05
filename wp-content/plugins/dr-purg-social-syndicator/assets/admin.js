(function ($) {
  function setMediaPreview(field, attachment) {
    const input = field.find('[data-dpj-media-input]');
    const preview = field.find('[data-dpj-media-preview]');
    input.val(attachment && attachment.id ? String(attachment.id) : '0');

    if (attachment && attachment.sizes) {
      const image = attachment.sizes.medium || attachment.sizes.thumbnail || attachment.sizes.full;
      if (image && image.url) {
        preview.html('<img class="dpj-selected-image" src="' + image.url + '" alt="">');
        return;
      }
    }

    preview.html('<span class="dpj-media-empty">No image selected</span>');
  }

  $(document).on('click', '[data-dpj-media-select]', function (event) {
    event.preventDefault();

    const field = $(this).closest('.dpj-media-field');
    const frame = wp.media({
      title: 'Choose social image',
      button: { text: 'Use this image' },
      multiple: false
    });

    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      setMediaPreview(field, attachment);
    });

    frame.open();
  });

  $(document).on('click', '[data-dpj-media-clear]', function (event) {
    event.preventDefault();
    setMediaPreview($(this).closest('.dpj-media-field'), null);
  });

  function countWords(value) {
    const cleaned = String(value || '')
      .replace(/https?:\/\/\S+/gi, '')
      .replace(/\s+/g, ' ')
      .trim();
    return cleaned === '' ? 0 : cleaned.split(' ').length;
  }

  function updateOverlayCounter(counter) {
    const input = document.querySelector(counter.getAttribute('data-dpj-overlay-input'));
    if (!input) {
      return;
    }

    const max = parseInt(counter.getAttribute('data-dpj-overlay-max'), 10) || 12;
    const idealMin = parseInt(counter.getAttribute('data-dpj-overlay-ideal-min'), 10) || 8;
    const min = parseInt(counter.getAttribute('data-dpj-overlay-min'), 10) || 3;
    const words = countWords(input.value);

    let level = 'ok';
    let message = words + ' words';
    if (words === 0) {
      level = 'warning';
      message = 'No overlay text yet';
    } else if (words > max) {
      level = 'warning';
      message = words + ' words – only the first ' + max + ' are kept on the cards';
    } else if (words < min) {
      level = 'warning';
      message = words + ' words – too short for a hook (aim for ' + idealMin + '–' + max + ')';
    } else if (words < idealMin) {
      level = 'notice';
      message = words + ' words – ' + idealMin + '–' + max + ' usually reads stronger';
    } else {
      level = 'ok';
      message = words + ' words – good hook length';
    }

    counter.textContent = message;
    counter.setAttribute('data-dpj-level', level);
  }

  $('[data-dpj-overlay-counter]').each(function () {
    const counter = this;
    const input = document.querySelector(counter.getAttribute('data-dpj-overlay-input'));
    if (!input) {
      return;
    }

    updateOverlayCounter(counter);
    $(input).on('input', function () {
      updateOverlayCounter(counter);
    });
  });

  $(document).on('click', '[data-dpj-card-preview-btn]', function (event) {
    event.preventDefault();
    if (typeof dpjSocialPreview === 'undefined') {
      return;
    }

    const container = document.querySelector('[data-dpj-card-preview]');
    if (!container) {
      return;
    }

    const value = function (selector) {
      const el = document.querySelector(selector);
      return el ? el.value : '';
    };
    const checked = function (selector) {
      const el = document.querySelector(selector);
      return el && el.checked ? '1' : '0';
    };

    container.textContent = dpjSocialPreview.rendering;

    $.post(dpjSocialPreview.ajaxUrl, {
      action: 'dpj_social_card_preview',
      nonce: dpjSocialPreview.nonce,
      post_id: value('input[name="post_id"]'),
      overlay_text: value('#dpj-local-overlay-text'),
      overlay_enable: checked('input[name="local_overlay_enable"]'),
      overlay_pos: value('select[name="local_overlay_pos"]'),
      crop_focus: value('select[name="local_crop_focus"]'),
      hint_text: value('input[name="local_hint_text"]'),
      hint_enable: checked('input[name="local_hint_enable"]')
    }).done(function (response) {
      if (response && response.success && response.data && response.data.image) {
        const img = document.createElement('img');
        img.className = 'dpj-card-preview-img';
        img.src = response.data.image;
        img.alt = '';
        container.innerHTML = '';
        container.appendChild(img);
      } else {
        container.textContent = (response && response.data && response.data.message) ? response.data.message : dpjSocialPreview.failed;
      }
    }).fail(function () {
      container.textContent = dpjSocialPreview.failed;
    });
  });

  $(document).on('click', '[data-dpj-copy]', function (event) {
    event.preventDefault();
    const selector = $(this).attr('data-dpj-copy');
    const target = selector ? document.querySelector(selector) : null;
    if (!target) {
      return;
    }

    const value = target.value || target.textContent || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value);
      return;
    }

    target.focus();
    target.select();
    document.execCommand('copy');
  });
}(jQuery));
