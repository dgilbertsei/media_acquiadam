# Media: Acquia DAM asset type converter

This module provides a queue and drush command for converting Acquia DAM images from one filetype to another. 

**Usage:**

Enable the module and run:

    drush adtc tiff png

This will queue all tiff assets in Acquia DAM for conversion to png assets. See the drush command help for additional usage examples.

Processing of these assets is tied to how frequently Drupal cron runs. You can manually process the queue by running:

    drush queue:run media_acquiadam_asset_convert

The process length varies based on the number and size of assets.

Converted assets have a flag stored in the Acquia DAM assets data table so that they may be excluded from processing in the future. If assets need to be reprocessed then this value must be removed from the table. Efforts are also made to only convert assets when there is no destination asset with the same name in the same location.