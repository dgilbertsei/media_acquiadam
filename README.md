# Media: Acquia DAM

Acquia Digital Asset Manager (DAM) empowers enterprise creatives and marketers to create, organize, manage, and share all branded content for use through online and offline channels, with optimized asset publishing through the Acquia Platform.

A Digital Asset Management solution provides a centralized repository in the cloud to store, manage, provide access, and distribute large numbers of digital assets across a distributed organization.

* Centralization of assets makes assets available across a distributed organization
* Improved efficiency and cost reduction through findability and reuse of assets
* Enhancement of team collaboration
* Workflows which can ensure creation and distribution of the right assets
* Can be integrated with CMS, PIM, CRM and other solutions or channels
* Improved security and compliance
* Reporting tools to understand asset usage

## Installation

* Install the module and dependencies as normal.
* Create a background API user within Acquia DAM (see the background user section).
* Configure the background user credentials.

```php
$conf['media_acquiadam_background_user'] = 'username';
$conf['media_acquiadam_background_pass'] = 'password';
```

!!: These credentials are plain-text and should not be stored in the database by using any `variable_set` or `drush vset` style commands. It is highly recommended that these credentials are stored in a file outside of a web accessible path, and then included into the settings.php file.

See "Storing private information securely in Drupal": https://docs.acquia.com/article/storing-private-information-securely-drupal#conf

* Visit the module configuration page (admin/config/media/acquiadam) and set the Client ID and Secret.


## Configuration

**Client ID**: Your Acquia DAM client ID, available by contacting Acquia DAM support. (`drush vset media_acquiadam_client_id abc123`)

**Client Secret**: Your Acquia DAM client secret, available by contacting Acquia DAM support. (`drush vset media_acquiadam_client_secret  abc123`)


**Client mode: Background only**: The background-only client mode forces the DAM connector to leverage the background user for all connections. This limits Drupal and all users to viewing and downloading assets that are accessible to the background user. (`drush vset media_acquiadam_client_mode background`)

**Client mode: Mixed**: Mixed client mode allows users to authenticate with their credentials when choosing and viewing assets. This mode enables broader permission and group controls -- enabling two different users to initially see different sets of assets -- while still providing access to anonymous users. (`drush vset media_acquiadam_client_mode mixed`)

**Used cache lifetime**: The time, in minutes, to cache asset information that is being used within Drupal. Some asset information is stored locally for performance reasons when displaying assets. This cache timeout setting should be as high as allowable. Setting this value to 0 will disable caching for used assets. (`drush vset media_acquiadam_cache_expiration 1440`)

**Unused cache lifetime**: The time, in minutes, to cache asset information that was loaded while using the asset browser. This information is cached locally for performance reasons while using the browser, but is not used otherwise. A short timeout (5 to 30 minutes) is recommended so a user does not see stale information in the browser. Setting this value to 0 will disable caching for unused assets. (`drush vset media_acquiadam_unused_expiration 30`)

**Mimetype adjustment**: By default some asset types are treated in a manner that may not be desired. For example, an EPS file will be handled by Drupal as a document and only allow the user to download. This setting allows the site administrator to instruct Drupal to treat certain DAM assets as other file types. In the case of EPS files, Drupal can be instructed to treat and render them as it would PNG files. (`drush vset media_acquiadam_extension_overrides "eps png"`)

**Results per page**: The number of assets, not including folders, to display per page of results in the asset browser. (`drush vset media_acquiadam_browser_per_page 25`)

### Background user

A separate DAM user must be configured for full functionality of the module. An existing account should not be used in case of compromise. This user is referred to as the "background user" or "API user" and is used whenever Drupal or an anonymous visitor needs to request details about an asset.

This new user should be the minimum account type that has the permissions desired (e.g., "Brand Portal" or "Regular user").

A separate "API" or "Drupal" group should be created and assigned to the background user. This group should have permissions to both view and download any assets that are expected to be included within the Drupal website. If the background user does not have access to an asset then it is possible that assets may not expire within Drupal at the appropriate times or anonymous visitors cannot download or view certain assets. Write access is not necessary for any features within Drupal at this time.

### "Unknown" file types (Missing mimetypes in Drupal)

If an asset is added to Drupal and receives an "Unknown" file type it is because Drupal is not aware of the file's mimetype. To fix this situation the site administrator must go to admin/structure/file-types and edit the file type by adding the missing mimetype. After the mimetype has been added the asset should be removed from Drupal and then re-imported.

To avoid this issue the site administrator should add the mimetypes of files that will be used on Drupal before providing access to editors and other users.

## Cached asset behavior

Some asset information, such as name, thumbnail URLs, and expiration dates, are cached locally within Drupal. This is primarily for performance reasons so that external site requests are not made every time an asset needs to be displayed. This asset cache can be disabled by setting the used asset timeout configuration option to 0.

Clearing Drupal cache, through Drush or other means, will not clear the asset cache. The asset cache has its own cache table (`acquiadam_asset_cache`). The asset cache can be cleared either by going to Acquia DAM configuration page and using the cache features there, or truncating the database table directly. There will be a performance impact until assets are re-cached and thumbnails are re-created.

Individual assets can be re-cached by viewing their entity pages within Drupal and using the "Force refresh from Acquia DAM" link. The asset will be re-cached immediately while leaving the rest of the cached assets untouched.

Asset cache is also refreshed periodically through cron jobs and normal module usage.

## Expiring assets

Within Acquia DAM it is possible to set assets to expire on a certain date. When expired these assets will also no longer be usable within the Acquia DAM browser and Drupal itself. Assets that have been added to Drupal and have expired may no longer appear in listings for some users, and may not render when viewing content that once contained them.

Asset expiration within Drupal may not be immediate, and can depend on how frequently Drupal cron runs and the cache timeout configured on the settings page. If an asset must be expired immediately, the "Force refresh from Acquia DAM" link may be used.

Assets who have an expiration date adjusted to be sooner than it originally was may not expire on time for the same reasons. Again, usage of the "Force refresh from Acquia DAM" link is suggested.

Behavior of an expired asset within Drupal will depend on how that asset is being used. If the asset has been added to a field then it should no longer be rendered within that field. If the asset has been embedded as an entity within a WYSIWYG editor, then it should also no longer render. In both of these scenarios the Drupal cache may need to be cleared before the asset markup is no longer rendered, but the image itself should not render. If the asset image URL was inserted directly then the result depends on the user's browser. It is recommended to always use assets within a field or as embedded media entity items.

## Asset thumbnails

Asset thumbnails in Drupal are created from a DAM-provided image preset that is a maximum of 1280 in width or height (whichever is larger). Images smaller than 1280 may use a smaller source thumbnail size instead. Thumbnails are deleted when an asset is deleted from Drupal or its cache is cleared.

### Displaying image assets directly

Some assets -- such as PDF files -- may display their CDN-backed links without special configuration. Other assets will be run through a Drupal link wrapper or the image style system.

In order to display CDN-backed images the site administrator must configure the view mode being used to use the original image, and not an image style. Whenever Drupal renders the original image it will provide the CDN-backed URL. At this time only the 1280 CDN source can be used.

## Module-provided hooks

See `media_acquiadam_browser.api.php`.

## Permissions

**administer media acquiadam**: Allows the role to administer Acquia DAM settings through the configuration page. Only administrators and site builders should have this permission.

**view acquiadam assets**: Allows the role to view Acquia DAM assets. All roles should have this permission on most sites.

**view expired acquiadam assets**: Allows the role to access and edit expired assets within Drupal. This should be given to trusted roles only. Note: This will not enable the role to view the actual asset since that is controlled within Acquia DAM.

**refresh acquiadam assets**: Allows the role to refresh cache on demand when visiting an asset's page. Because of the performance implications this should be given to trusted roles only.

**view acquiadam links**: Allows the role to view links that go to the asset within Acquia DAM.

**access media acquiadam browser**: Allows the role to access the Media: Acquia DAM asset browser. If the browser is configured to use mixed mode the user must still authenticate against Acquia DAM.
