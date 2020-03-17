/**
 * @file
 * Resize the asset browser frame.
 */

(function ($, Drupal) {

  Drupal.behaviors.acquiadamAssetBrowser = {
    attach: function () {
      $(".acquiadam-asset-browser").height($(window).height() - $(".filter-sort-container").height() - 175);
      $(window).on('resize', function () {
        $(".acquiadam-asset-browser").height($(window).height() - $(".filter-sort-container").height() - 175);
      });
    }
  };

})(jQuery, Drupal);
