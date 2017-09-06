(function ($) {
  'use strict';

  // Code borrowed and modified from media.admin.js.
  var mediaMediaItemSelectToggle = function () {
    // If the media item is clicked anywhere other than on the image itself
    // check the checkbox. For the record, JS thinks this is wonky.
    $('.browser-asset', this).bind('click', function (e) {
      if ($(e.target).parents('.jump-list').length) {
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
    $('#media-acquiadam-browser-library-list :checkbox, #media-acquiadam-browser-library-list :radio', this).each(function () {
      var checkbox = $(this);
      if (checkbox.is(':checked')) {
        $(checkbox).parents('li').addClass('selected');
      }

      checkbox.bind('change.media', function () {
        if (checkbox.is(':checked')) {
          $(checkbox).parents('li').addClass('selected');
        }
        else {
          $(checkbox).parents('li').removeClass('selected');
        }
      });
    });
  };

  Drupal.behaviors.mediaAcquiaDAMBrowser = {
    attach: function (context, settings) {

      $('.media-acquiadam-browser-assets', context).once(mediaMediaItemSelectToggle);

      $('#media-acquiadam-browser-choose-asset-form .back-link', context)
        .once(function () {

          $(this).bind('click', function (e) {
              e.preventDefault();
              history.back(1);
            });
        });
    }
  };

})(jQuery);
