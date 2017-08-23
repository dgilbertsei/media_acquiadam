<?php

/**
 * @file
 * Template implementation for assets displayed within the Media Browser plugin.
 *
 * Variables:
 *  $asset: The asset object being rendered.
 *  $browser_url: The current Media Browser URL.
 *  $thumbnail: The thumbnail markup to use.
 */
?>
<div class="media-item" data-asset-type="<?php echo $asset['type'] ?>" data-asset-id="<?php echo $asset['id'] ?>" data-asset-status="<?php echo $asset['status'] ?>">
  <?php if (!empty($jump_list)) : ?>
    <?php echo $jump_list ?>
  <?php endif; ?>
  <div class="media-thumbnail">
    <?php if ($asset['type'] == 'folder') : ?>
      <a href="<?php print $browser_url; ?>"><?php echo $thumbnail?></a>
    <?php else : ?>
      <?php echo $thumbnail?>
    <?php endif; ?>
    <div class="label-wrapper">
      <label class="media-filename">
      <?php if ($asset['type'] == 'folder') : ?>
        <a href="<?php print $browser_url; ?>"><?php echo $asset['name'] ?></a>
      <?php else : ?>
        <?php echo $asset['name'] ?>
      <?php endif; ?>
      </label>
    </div>
  </div>
</div>
