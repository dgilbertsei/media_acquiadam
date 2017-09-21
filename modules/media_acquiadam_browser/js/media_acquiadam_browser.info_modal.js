(function ($) {
  'use strict';

  function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
  };

  Drupal.behaviors.mediaAcquiaDAMInfoModal = {
    attach: function (context, settings) {
      if ('/media/browser' == location.pathname) {
        $('#modal-content .asset-info .property-keyword-value a', context)
        .once()
        .each(function () {
          var params = {
            render: getUrlParameter('render'),
            types: getUrlParameter('types'),
            enabledPlugins: getUrlParameter('enabledPlugins'),
            options: getUrlParameter('options'),
            plugins: getUrlParameter('plugins'),
            search: {
              keywords: $(this).text()
            }
          };
          $(this).attr('href', location.pathname + '?' + $.param(params));
        });
      }
    }
  };

})(jQuery);
