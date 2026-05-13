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
