(function ($) {
  'use strict';

  var mediaMediaItemSelectToggle = function () {
    // If the media item is clicked anywhere other than on the image itself
    // check the checkbox. For the record, JS thinks this is wonky.
    $('.media-item', this).bind('click', function (e) {
      if ($(e.target).is('img, a')) {
        return;
      }
      var checkbox = $(this).parent().find(':checkbox, :radio');
      if (checkbox.is(':checked')) {
        checkbox.attr('checked', false).change();
      } else {
        checkbox.attr('checked', true).change();
      }
    });

    // Add an extra class to selected thumbnails.
    $('.media-display-thumbnails :checkbox, .media-display-thumbnails :radio', this).each(function () {
      var checkbox = $(this);
      if (checkbox.is(':checked')) {
        $(checkbox.parents('li').find('.media-item')).addClass('selected');
      }

      checkbox.bind('change.media', function () {
        if (checkbox.is(':checked')) {
          $(checkbox.parents('li').find('.media-item')).addClass('selected');
        }
        else {
          $(checkbox.parents('li').find('.media-item')).removeClass('selected');
        }
      });
    });
  };

  Drupal.behaviors.mediaWebdamBrowser = {
    attach: function (context, settings) {

      $('.media-webdam-browser-assets', context).once(mediaMediaItemSelectToggle);
    }
  };

})(jQuery);
