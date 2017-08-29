# Media: Acquia DAM

Provides a strong integration with the Acquia DAM service, allowing users to easily include Acquia DAM assets within their Drupal site.

## Installation

* Install the module and dependencies as normal.
* Create a background API user within Acquia DAM (see the background user section).
* Configure the background user credentials.

```php
$conf['media_webdam_background_user'] = 'username';
$conf['media_webdam_background_pass'] = 'password';
```

!!: These credentials are plain-text and should not be stored in the database by using any `variable_set` or `drush vset` style commands. It is highly recommended that these credentials are stored in a file outside of a web accessible path, and then included into the settings.php file.

See "Storing private information securely in Drupal": https://docs.acquia.com/article/storing-private-information-securely-drupal#conf

* Visit the module configuration page and set the Client ID and Secret.

## Background user

A separate user must be configured for full functionality of the module. An existing account should not be used in case of compromise. This user is referred to as the background, or API, user and is used whenever the system or anonymous user needs to request details about an asset.

This new user should be the minimum account type that has the permissions desired (e.g., "Brand Portal" or "Regular user").

A separate "API" or "Drupal" group should be created and assigned to the background user. This group should have permissions to both view and download any assets that are expected to be included within the Drupal website. If the background user does not have access to an asset then it is possible that assets may not expire within Drupal at the apropriate times or anonymous users cannot download certain assets. Write access is not necessary.

## Client modes

**Client mode: Background only**: The background-only client mode forces the DAM connector to leverage the background user for all connections. This limits Drupal and all users to viewing and downloading assets that are accessible to the background user.

**Client mode: Mixed**: Mixed client mode allows users to authenticate with their credentials when choosing and viewing assets. This mode enables broader permission and group controls -- enabling two different users to initially see different sets of assets -- while still providing access to anonymous users.
