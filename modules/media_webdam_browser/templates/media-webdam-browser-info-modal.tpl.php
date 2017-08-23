<?php

/**
 * @file
 * Template implementation for asset information displayed inside a modal..
 *
 * Variables:
 *  $asset: The asset object being rendered.
 *  $preview: The preview markup to use. Usually an image.
 */
?>
<div class="asset-info">
  <?php if (!empty($preview)) : ?>
  <div class="preview">
    <?php print $preview ?>
  </div>
  <?php endif; ?>
  <div class="properties">
    <?php print $properties ?>
  </div>
</div>
