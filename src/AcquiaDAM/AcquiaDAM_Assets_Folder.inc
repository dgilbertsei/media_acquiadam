<?php

/**
 * Acquia DAM asset implmentation for Folder assets.
 *
 * @package AcquiaDAM
 */
class AcquiaDAM_Assets_Folder extends AcquiaDAM_Assets_AbstractAsset {

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
   * Get the asset type identifier.
   *
   * {@inheritDoc}
   *
   * @return string
   *   The asset type machine name.
   */
  public function getType() {
    return 'folder';
  }

  /**
   * Retrieve the Root folder listing.
   *
   * @return AcquiaDAM_Assets_Folder[]
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
   * @param bool $fullyLoad
   *   TRUE to fully load returned assets.
   *
   * @return array
   *   An array containing child folders and asset items.
   */
  public function getAssets(array $options = [], $fullyLoad = FALSE) {

    $this->requireId();

    $options += [
      'sortby' => 'filename',
      'sortdir' => 'desc',
      'limit' => 50,
      'offset' => 0,
    ];

    $result = $this->request(sprintf('folders/%d/assets', $this->asset['id']), $options);
    $mapping = [
      'items' => 'asset',
      'folders' => 'folder',
    ];
    foreach ($mapping as $key => $type) {
      if (!empty($result[$key]) && is_array($result[$key])) {
        $result[$key] = array_map(function ($item) use ($type, $fullyLoad) {
          $id = $fullyLoad ? $item['id'] : $item;
          return media_acquiadam_api_get_helper($type, $id, $this->depends);
        }, $result[$key]);
      }
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

  /**
   * Gets the path to this asset within the DAM web interface.
   *
   * {@inheritDoc}
   */
  public function getDAMPath() {

    $this->get();

    return sprintf('cloud/#folder/%d', $this->asset['id']);
  }

}
