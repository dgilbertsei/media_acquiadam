<?php

namespace Drupal\media_acquiadam\Entity;

/**
 * Class Category.
 *
 * Describes Acquia DAM's Category data type.
 */
class Category implements EntityInterface, \JsonSerializable {

  /**
   * ID of the Category.
   *
   * @var string
   */
  public $id;

  /**
   * Name of the Category.
   *
   * @var string
   */
  public $name;

  /**
   * Path of the category.
   *
   * @var string
   */
  public $path;

  /**
   * Parts information, useful in rendering breadcrumbs.
   *
   * @var array
   */
  public $parts = [];

  /**
   * An array of sub categories.
   *
   * @var Category[]
   */
  public $categories;

  /**
   * Links contains array of asset and categories.
   *
   * @var array
   */
  public $links;

  /**
   * Get category object.
   *
   * @param mixed $json
   *   Json object contain categories data.
   *
   * @return object
   *   Category data.
   */
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
    elseif (isset($json->total_count) && $json->total_count === 0) {
      return $subCategories;
    }
    $properties = [
      'id',
      'name',
      'path',
      'parts',
      'links',
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

  /**
   * Serialize the category data.
   *
   * @return array
   *   Array contain category properties.
   */
  public function jsonSerialize() {
    $properties = [
      'id' => $this->id,
      'type' => 'category',
      'name' => $this->name,
      'path' => $this->path,
      'parts' => $this->parts,
      'links' => $this->links,
    ];

    if (!empty($this->categories)) {
      $properties['categories'] = $this->categories;
    }

    return $properties;
  }

}
