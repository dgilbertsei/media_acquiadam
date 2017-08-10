<?php

/**
 * Webdam Search requests.
 *
 * @package Webdam
 */
class Webdam_Search {

  /**
   * A list of class dependencies.
   *
   * @var array
   */
  protected $depends = [];

  /**
   * Webdam_Search constructor.
   *
   * @param array $depends
   *   An array of class dependencies.
   */
  public function __construct(array $depends = []) {
    $this->depends = $depends;

    if (empty($this->depends['client'])) {
      $this->depends['client'] = Webdam_API::getClient();
    }
  }

  /**
   * Issue a search.
   *
   * @param string $phrase
   *   The keywords to search for.
   * @param array $options
   *   An array of options to pass to to the API request.
   *
   * @return array
   *   The result of the search request.
   */
  public function search($phrase, array $options = []) {

    $options += [
      'query' => $phrase,
      'folderid' => NULL,
      'sortby' => 'datecreated',
      'sortdir' => 'asc',
      'limit' => 50,
      'offset' => 0,
      'types' => '',
      'searchfield' => '',
    ];

    if (is_array($options['types'])) {
      $options['types'] = implode(',', $options['types']);
    }

    return $this->depends['client']->request('search', $options);
  }

}
