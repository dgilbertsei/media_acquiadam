<?php

namespace Drupal\media_acquiadam;

use Drupal;

/**
 * Bridge class for the transition of unmanaged file operations to a class.
 */
class FileSystemBridge {

  /**
   * Flag for dealing with existing files: Appends number until name is unique.
   */
  const EXISTS_RENAME = 0;

  /**
   * Flag for dealing with existing files: Replace the existing file.
   */
  const EXISTS_REPLACE = 1;

  /**
   * Flag for dealing with existing files: Do nothing and return FALSE.
   */
  const EXISTS_ERROR = 2;

  /**
   * Flag used by ::prepareDirectory() -- create directory if not present.
   */
  const CREATE_DIRECTORY = 1;

  /**
   * Flag used by ::prepareDirectory() -- file permissions may be changed.
   */
  const MODIFY_PERMISSIONS = 2;

  /**
   * Bridge to use the correct prepare function based on Drupal version.
   *
   * @param string $directory
   *   A string reference containing the name of a directory path or URI.
   * @param int $options
   *   A bitmask to indicate if the directory should be created.
   *
   * @return bool
   *   TRUE if the directory exists (or was created) and is writable.
   */
  public static function prepareDirectory(&$directory, $options = self::MODIFY_PERMISSIONS) {
    // phpcs:disable DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $file_system = Drupal::service('file_system');
    // phpcs:enable
    if (method_exists($file_system, 'prepareDirectory')) {
      return $file_system->prepareDirectory($directory, $options);
    }

    return file_prepare_directory($directory, $options);
  }

  /**
   * Bridge to use the correct copy function based on Drupal version.
   *
   * @param string $source
   *   A string specifying the filepath or URI of the source file.
   * @param string $destination
   *   A URI containing the destination that $source should be copied to.
   * @param int $replace
   *   Replace behavior when the destination file already exists.
   *
   * @return string|false
   *   The path to the new file, or FALSE in the event of an error.
   */
  public static function copy($source, $destination, $replace = self::EXISTS_RENAME) {
    // phpcs:disable DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $file_system = Drupal::service('file_system');
    // phpcs:enable
    if (method_exists($file_system, 'copy')) {
      return $file_system->copy($source, $destination, $replace);
    }

    return file_unmanaged_copy($source, $destination, $replace);
  }

  /**
   * Bridge to use the correct save function based on Drupal version.
   *
   * @param string $data
   *   A string containing the contents of the file.
   * @param string $destination
   *   A string containing the destination location.
   * @param int $replace
   *   Replace behavior when the destination file already exists.
   *
   * @return string|false
   *   A string with the path of the resulting file, or FALSE on error.
   */
  public static function saveData($data, $destination = NULL, $replace = self::EXISTS_RENAME) {
    // phpcs:disable DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $file_system = Drupal::service('file_system');
    // phpcs:enable
    if (method_exists($file_system, 'saveData')) {
      return $file_system->saveData($data, $destination, self::EXISTS_REPLACE);
    }

    return file_unmanaged_save_data($data, $destination, $replace);
  }

}
