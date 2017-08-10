<?php

/**
 * Webdam asset implmentation for Folder assets.
 *
 * @package Webdam
 */
class Webdam_Assets_Folder extends Webdam_Assets_AbstractAsset {

  /**
   * The ID of what is considered the Root folder.
   *
   * @var int
   */
  const ROOT_FOLDER_ID = 0;

  /**
   * Return the API endpoint base.
   *
   * {@inheritDoc}
   *
   * @return string
   *   The base slug to use in API requests.
   */
  protected function getEndpointBase() {
    return 'folders';
  }

  /**
   * Retrieve the Root folder listing.
   *
   * @return Webdam_Assets_Folder[]
   *   An array of folders located at the root.
   */
  public function getRoot() {
    $result = [];

    $rootFolders = $this->request('folders');
    if (!empty($rootFolders)) {
      foreach ($rootFolders as $folder) {
        $result[] = new static($folder, $this->depends);
      }
    }

    return $result;
  }

  /**
   * Get assets located in the folder.
   *
   * @param array $options
   *   Options to pass to the API request.
   *
   * @return array
   *   An array containing child folders and asset items.
   */
  public function getAssets(array $options = []) {

    $this->requireId();

    $options += [
      'sortby' => 'filename',
      'sortdir' => 'desc',
      'limit' => 50,
      'offset' => 0,
    ];

    $result = $this->request(sprintf('folders/%d/assets', $this->asset['id']), $options);
    if (!empty($result['items']) && is_array($result['items'])) {
      $result['items'] = array_map(function ($item) {
        return new Webdam_Assets_Asset($item, $this->depends);
      }, $result['items']);
    }
    if (!empty($result['folders']) && is_array($result['folders'])) {
      $result['folders'] = array_map(function ($item) {
        return new static($item, $this->depends);
      }, $result['folders']);
    }

    return $result;
  }

  /**
   * Get parents of this folder.
   *
   * @param int[] $parents
   *   An array of existing parent IDs.
   *
   * @return int[]
   *   An array of parent IDs.
   */
  public function getParents(array $parents = []) {

    if (!empty($this['parent'])) {

      $parents[] = $this['parent'];
      $parent = new static($this['parent']);
      return $parent->getParents($parents);
    }

    return $parents;
  }

}
