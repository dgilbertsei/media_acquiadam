<?php

/**
 * @file
 * Describes Acquia DAM's Category data type.
 */

namespace Drupal\acquiadam\Entity;

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
   * @var Category[] $categories
   */
  public $categories;

  public static function fromJson($json) {
    if (is_string($json)) {
      $json = json_decode($json);
    }

    $properties = [
      'id',
      'name',
      'path',
    ];

    $category = new static();
    foreach ($properties as $property) {
      if (isset($json->{$property})) {
        $category->{$property} = $json->{$property};
      }
    }

    // Add Categories objects.
    $categories = [];
    if (!empty($json->items)) {
      foreach ($json->items as $category_data) {
        $categories[] = Category::fromJson($category_data);
      }
    }
    $category->categories = $categories;

    return $category;
  }

  public function jsonSerialize() {
    $properties = [
      'id' => $this->id,
      'type' => 'category',
      'name' => $this->name,
      'path' => $this->path,
    ];

    if (!empty($this->categories)) {
      $properties['categories'] = $this->categories;
    }

    return $properties;
  }

}
