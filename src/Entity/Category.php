<?php

/**
 * @file
 * Describes Acquia DAM's Category data type.
 */

namespace Drupal\media_acquiadam\Entity;

class Category implements EntityInterface, \JsonSerializable {

  /**
   * @var string $id
   */
  public $id;

  /**
   * @var string $name
   */
  public $name;

  /**
   * @var string $path
   */
  public $path;

  /**
   * @var array $parts
   * Parts information, useful in rendering breadcrumbs.
   */
  public $parts = [];
  /**
   * @var Category[] $categories
   */
  public $categories;

  /**
   * @var array $_links
   */
  public $_links;

  public static function fromJson($json) {
    if (is_string($json)) {
      $json = json_decode($json);
    }

    $subCategories = [];
    if (isset($json->total_count) && $json->total_count > 0) {
      foreach ($json->items as $subcategory_data) {
        $subCategories[] = Category::fromJson($subcategory_data);
      }
      return $subCategories;
    }
    else if (isset($json->total_count) && $json->total_count === 0) {
      return $subCategories;
    }
    $properties = [
      'id',
      'name',
      'path',
      'parts',
      '_links'
    ];

    $category = new static();
    foreach ($properties as $property) {
      if (isset($json->{$property})) {
        $category->{$property} = $json->{$property};
      }
    }

    $category->categories = isset($json->items) ? $json->items : [];

    return $category;
  }

  public function jsonSerialize() {
    $properties = [
      'id' => $this->id,
      'type' => 'category',
      'name' => $this->name,
      'path' => $this->path,
      'parts' => $this->parts,
      '_links' => $this->_links
    ];

    if (!empty($this->categories)) {
      $properties['categories'] = $this->categories;
    }

    return $properties;
  }

}
