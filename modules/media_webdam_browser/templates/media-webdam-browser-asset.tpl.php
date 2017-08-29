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

<div class="browser-asset" data-asset-type="<?php echo $asset['type'] ?>" data-asset-id="<?php echo $asset['id'] ?>" data-asset-status="<?php echo $asset['status'] ?>">

  <div class="preview">
    <?php if ($asset['type'] == 'folder') : ?>
      <div class="folder-tab"></div>
    <?php endif; ?>

    <div class="preview-body">
      <?php if ($asset['type'] == 'folder') : ?>
        <a href="<?php print $browser_url; ?>"><?php echo $thumbnail?></a>
      <?php else : ?>
        <?php echo $thumbnail?>
      <?php endif; ?>
    </div>
  </div>

  <div class="label" title="<?php echo check_plain($asset['name']) ?>">
    <?php if ($asset['type'] == 'folder') : ?>
      <a href="<?php print $browser_url; ?>"><?php echo $asset['name'] ?></a>
    <?php else : ?>
      <?php echo $asset['name'] ?>
    <?php endif; ?>
  </div>

  <?php if (!empty($jump_list)) : ?>
  <?php echo $jump_list ?>
  <?php endif; ?>
</div>
