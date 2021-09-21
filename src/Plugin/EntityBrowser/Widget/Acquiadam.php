<?php

namespace Drupal\acquiadam\Plugin\EntityBrowser\Widget;

use Drupal\acquiadam\Entity\Asset;
use Drupal\acquiadam\Entity\Category;
use Drupal\acquiadam\Exception\InvalidCredentialsException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\WidgetBase;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\media\MediaSourceManager;
use Drupal\acquiadam\AcquiadamInterface;
use Drupal\acquiadam\Form\AcquiadamConfig;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Uses a view to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "acquiadam",
 *   label = @Translation("Acquia DAM"),
 *   description = @Translation("Acquia DAM asset browser"),
 *   auto_select = FALSE
 * )
 */
class Acquiadam extends WidgetBase {

  /**
   * The dam interface.
   *
   * @var \Drupal\acquiadam\AcquiadamInterface
   */
  protected $acquiadam;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * A module handler object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A media source manager.
   *
   * @var \Drupal\media\MediaSourceManager
   */
  protected $sourceManager;

  /**
   * An entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * User data manager.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Drupal RequestStack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Acquiadam constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, WidgetValidationManager $validation_manager, AcquiadamInterface $acquiadam, AccountInterface $account, LanguageManagerInterface $languageManager, ModuleHandlerInterface $moduleHandler, MediaSourceManager $sourceManager, UserDataInterface $userData, RequestStack $requestStack, ConfigFactoryInterface $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager);
    $this->acquiadam = $acquiadam;
    $this->user = $account;
    $this->languageManager = $languageManager;
    $this->moduleHandler = $moduleHandler;
    $this->sourceManager = $sourceManager;
    $this->entityFieldManager = $entity_field_manager;
    $this->userData = $userData;
    $this->requestStack = $requestStack;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'), $container->get('entity_type.manager'), $container->get('entity_field.manager'), $container->get('plugin.manager.entity_browser.widget_validation'), $container->get('acquiadam.acquiadam_user_creds'), $container->get('current_user'), $container->get('language_manager'), $container->get('module_handler'), $container->get('plugin.manager.media.source'), $container->get('user.data'), $container->get('request_stack'), $container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   *
   * @todo Add more settings for configuring this widget.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $media_type_options = [];
    $media_types = $this->entityTypeManager->getStorage('media_type')
      ->loadByProperties(['source' => 'acquiadam_asset']);

    foreach ($media_types as $media_type) {
      $media_type_options[$media_type->id()] = $media_type->label();
    }

    if (empty($media_type_options)) {
      $url = Url::fromRoute('entity.media_type.add_form')->toString();
      $form['media_type'] = [
        '#markup' => $this->t("You don't have media type of the Acquia DAM asset type. You should <a href='!link'>create one</a>", ['!link' => $url]),
      ];
    }
    else {
      $form['media_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Media type'),
        '#default_value' => $this->configuration['media_type'],
        '#options' => $media_type_options,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'media_type' => NULL,
      'submit_text' => $this->t('Select assets'),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $config = $this->config->get('acquiadam.settings');

    $media_type_storage = $this->entityTypeManager->getStorage('media_type');
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    if (!$this->configuration['media_type'] || !($media_type = $media_type_storage->load($this->configuration['media_type']))) {
      return ['#markup' => $this->t('The media type is not configured correctly.')];
    }
    elseif ($media_type->getSource()->getPluginId() != 'acquiadam_asset') {
      return ['#markup' => $this->t('The configured media type is not using the acquiadam_asset plugin.')];
    }
    // If this is not the current entity browser widget being rendered.
    elseif ($this->uuid() != $form_state->getStorage()['entity_browser_current_widget']) {
      return [];
    }

    // If the current user is not authenticated over Acquia DAM, display an
    // error message with invitation to authenticate via his user edit form.
    $auth = $this->acquiadam->getAuthState();
    if (empty($auth['valid_token'])) {
      $message = $this->t('You are not authenticated. Please %authenticate to browse Acquia DAM assets.', [
        '%authenticate' => Link::createFromRoute('authenticate', 'entity.user.edit_form', [
          'auth_finish_redirect' => $this->requestStack->getCurrentRequest()
            ->getRequestUri(),
        ])->toString(),
      ]);

      $form['message'] = [
        '#theme' => 'asset_browser_message',
        '#message' => $message,
        '#attached' => [
          'library' => [
            'acquiadam/asset_browser',
          ],
        ],
      ];
      return $form;
    }

    // Start by inheriting parent form.
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    // Attach the modal library.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    // This form is submitted and rebuilt when a category is clicked.
    // The triggering element identifies which category button was clicked.
    $trigger_elem = $form_state->getTriggeringElement();

    // Initialize current_category.
    $current_category = new Category();
    // Default current category id to zero which represents the root category.
    $current_category->id = 0;
    // Default current category parent id to zero which represents root category.
    $current_category->parent = 0;
    // Default current category name to 'Home' which represents the root category.
    $current_category->name = 'Home';
    // Default current page to first page.
    $page = 0;
    // Number of assets to show per page.
    $num_per_page = $config->get('num_images_per_page') ?? AcquiadamConfig::NUM_IMAGES_PER_PAGE;
    // Initial breadcrumb array representing the root category only.
    $breadcrumbs = [
      '0' => 'Home',
    ];
    // If the form state contains the widget AND the reset button hadn't been
    // clicked then pull values for the current form state.
    if (isset($form_state->getCompleteForm()['widget']) && isset($trigger_elem) && $trigger_elem['#name'] != 'filter_sort_reset') {
      // Assign $widget for convenience.
      $widget = $form_state->getCompleteForm()['widget'];
      if (isset($widget['pager-container']) && is_numeric($widget['pager-container']['#page'])) {
        // Set the page number to the value stored in the form state.
        $page = intval($widget['pager-container']['#page']);
      }
      if (isset($widget['asset-container']) && is_numeric($widget['asset-container']['#acquiadam_category_id'])) {
        // Set current category id to the value stored in the form state.
        $current_category->id = $widget['asset-container']['#acquiadam_category_id'];
      }
      if (isset($widget['breadcrumb-container']) && is_array($widget['breadcrumb-container']['#breadcrumbs'])) {
        // Set the breadcrumbs to the value stored in the form state.
        $breadcrumbs = $widget['breadcrumb-container']['#breadcrumbs'];
      }
      if ($form_state->getValue('assets')) {
        $current_selections = $form_state->getValue('current_selections', []) + array_filter($form_state->getValue('assets', []));
        $form['current_selections'] = [
          '#type' => 'value',
          '#value' => $current_selections,
        ];
      }
    }
    // If the form has been submitted.
    if (isset($trigger_elem)) {
      // If a category button has been clicked.
      if ($trigger_elem['#name'] === 'acquiadam_category') {
        // Set the current category id to the id of the category that was clicked.
        $current_category->id = $trigger_elem['#acquiadam_category_id'];
        $current_category->name = $trigger_elem['#value'];
        $current_category->subcategories_count = $trigger_elem['#acquiadam_subcategory_count'];
        // Reset page to zero if we have navigated to a new category.
        $page = 0;
      }
      // If a pager button has been clicked.
      if ($trigger_elem['#name'] === 'acquiadam_pager') {
        // Set the current category id to the id of the category that was clicked.
        $page = intval($trigger_elem['#acquiadam_page']);
      }
      // If the filter/sort submit button has been clicked.
      if ($trigger_elem['#name'] === 'filter_sort_submit') {
        // Reset page to zero.
        $page = 0;
      }
      // If the reset submit button has been clicked.
      if ($trigger_elem['#name'] === 'filter_sort_reset') {
        // Fetch the user input.
        $user_input = $form_state->getUserInput();
        // Fetch clean values keys (system related, not user input).
        $clean_val_key = $form_state->getCleanValueKeys();
        // Loop through user inputs.
        foreach ($user_input as $key => $item) {
          // Unset only the User Input values.
          if (!in_array($key, $clean_val_key)) {
            unset($user_input[$key]);
          }
        }
        // Reset the user input.
        $form_state->setUserInput($user_input);
        // Set values to user input.
        $form_state->setValues($user_input);
        // Rebuild the form state values.
        $form_state->setRebuild();
        // Get back to first page.
        $page = 0;
      }
    }
    // Offset used for pager.
    $offset = $num_per_page * $page;
    // Sort By field along with sort order.
    $sort_by = ($form_state->getValue('sortdir') == 'desc') ? '-' . $form_state->getValue('sortby') : $form_state->getValue('sortby');
    // Filter By asset type.
    $filter_type = $form_state->getValue('types') ? 'assettype:' . $form_state->getValue('types') : '';
    // Search keyword.
    $keyword = $form_state->getValue('query');
    // Generate search query based on search keyword and search filter.
    $search_query = trim($keyword . ' ' . $filter_type);
    // Parameters for searching, sorting, and filtering.
    $params = [
      'limit' => $num_per_page,
      'offset' => $offset,
      'sort' => $sort_by,
      'query' => $search_query,
      'expand' => 'thumbnails',
    ];
    // If the current category is not zero then fetch information about
    // the sub category being rendered.

    if ($current_category->id) {
      // If more subccategories are available and we should load them.
      // print $current_category->subcategories_count;
      if ($current_category->subcategories_count > 0) {
            $categories = $current_category = $this->acquiadam->getCategoryByName($current_category->name);
      }
      else {
        $params['query'] = 'category:' . $current_category->name;
        // @todo Find out how to list assets for the category
        $category_assets = $this->acquiadam->getAssetsByCategory($params);

        // If there is a filter applied for the file type.
        // if (!empty($params['types'])) {
        //   // Override number of assets on current category to make number of search
        //   // results so pager works correctly.
        //   $current_category->numassets = $category_assets->facets->types->{$params['types']};
        // }
        // Set items to array of assets in the current category.
        $items = $category_assets->items;

      }
    }
    else {
      // The root category is fetched differently because it can only
      // contain subcategories (not assets)
      $categories = $this->acquiadam->getTopLevelcategories();
    }
    // If searching by keyword.
    if (!empty($params['query'])) {
      // Format the query string as per the Widen API.
      $params['query'] = 'filename:(' . $params['query'] . ')';
      // Fetch search results.
      $search_results = $this->acquiadam->searchAssets($params);
      // Override number of assets on current category to make number of
      // search results so pager works correctly.
      // $current_category->numassets = $search_results['total_count'];
      // Set items to array of assets in the search result.
      $items = isset($search_results['assets']) ? $search_results['assets'] : [];
    }
    // Add the filter and sort options to the form.
    $form += $this->getFilterSort();
    // Add the breadcrumb to the form.
    // $form += $this->getBreadcrumb($current_category, $breadcrumbs);
    // Add container for assets (and category buttons)
    $form['asset-container'] = [
      '#type' => 'container',
      // Store the current category id in the form so it can be retrieved
      // from the form state.
      '#acquiadam_category_id' => $current_category->id,
      '#attributes' => [
        'class' => ['acquiadam-asset-browser'],
      ],
    ];

    // Get module path to create URL for background images.
    $modulePath = $this->moduleHandler->getModule('acquiadam')->getPath();

    // If no search terms, display Acquia DAM Categories.
    if (empty($params['query'])) {

      // Add category buttons to form.
      foreach ($categories as $category) {
        $this->getCategoryFormElements($category, $modulePath, $form);
      }
    }
    // Assets are rendered as #options for a checkboxes element.
    // Start with an empty array.
    $assets = [];
    $assets_status = [];
    // Add to the assets array.
    if (isset($items)) {
      foreach ($items as $category_item) {
        $assets[$category_item->id] = $this->layoutMediaEntity($category_item);
      }
    }
    // Add assets to form.
    // IMPORTANT: Do not add #title or #description properties.
    // This will wrap elements in a fieldset and will cause styling problems.
    // See: \core\lib\Drupal\Core\Render\Element\CompositeFormElementTrait.php.
    $form['asset-container']['assets'] = [
      '#type' => 'checkboxes',
      '#theme_wrappers' => ['checkboxes__acquiadam_assets'],
      '#title_display' => 'invisible',
      '#options' => $assets,
      '#attached' => [
        'library' => [
          'acquiadam/asset_browser',
        ],
      ],
    ];
    // If the number of assets in the current category is greater than
    // the number of assets to show per page.
    // if ($current_category->numassets > $num_per_page) {
    //   // Add the pager to the form.
    //   $form['actions'] += $this->getPager($current_category, $page, $num_per_page);
    // }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Create form elements for sorting and filtering/searching.
   */
  public function getFilterSort() {
    // Add container for pager.
    $form['filter-sort-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['filter-sort-container'],
      ],
    ];
    // Add dropdown for sort by.
    $form['filter-sort-container']['sortby'] = [
      '#type' => 'select',
      '#title' => 'Sort by',
      '#options' => [
        'filename' => 'File name',
        'size' => 'File size',
        'created_date' => 'Date created',
        'last_update_date' => 'Date modified',
      ],
      '#default_value' => 'created_date',
    ];
    // Add dropdown for sort direction.
    $form['filter-sort-container']['sortdir'] = [
      '#type' => 'select',
      '#title' => 'Sort direction',
      '#options' => ['asc' => 'Ascending', 'desc' => 'Descending'],
      '#default_value' => 'asc',
    ];
    // Add dropdown for filtering on asset type.
    $form['filter-sort-container']['types'] = [
      '#type' => 'select',
      '#title' => 'File type',
      '#options' => [
        '' => 'All',
        'image' => 'Image',
        'video' => 'Video',
        'document' => 'Document',
        'graphic' => 'Graphic',
        'other' => 'Other',
      ],
      '#default_value' => '',
    ];
    // Add textfield for keyword search.
    $form['filter-sort-container']['query'] = [
      '#type' => 'textfield',
      '#title' => 'Search',
      '#size' => 24,
    ];
    // Add submit button to apply sort/filter criteria.
    $form['filter-sort-container']['filter-sort-submit'] = [
      '#type' => 'button',
      '#value' => 'Apply',
      '#name' => 'filter_sort_submit',
    ];
    // Add form reset button.
    $form['filter-sort-container']['filter-sort-reset'] = [
      '#type' => 'button',
      '#value' => 'Reset',
      '#name' => 'filter_sort_reset',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getBreadcrumb(category $current_category, array $breadcrumbs = []) {
    // If the category being rendered is already in the breadcrumb trail
    // and the breadcrumb trail is longer than 1 (i.e. root category only)
    if (array_key_exists($current_category->id, $breadcrumbs) && count($breadcrumbs) > 1) {
      // This indicates that the user has navigated "Up" the category structure
      // 1 or more levels.
      do {
        // Go to the end of the breadcrumb array.
        end($breadcrumbs);
        // Fetch the category id of the last breadcrumb.
        $id = key($breadcrumbs);
        // If current category id doesn't match the category id of last breadcrumb.
        if ($id != $current_category->id && count($breadcrumbs) > 1) {
          // Remove the last breadcrumb since the user has navigated "Up"
          // at least 1 category.
          array_pop($breadcrumbs);
        }
        // If the category id of the last breadcrumb does not equal the current
        // category id then keep removing breadcrumbs from the end.
      } while ($id != $current_category->id && count($breadcrumbs) > 1);
    }
    // If the parent category id of the current category is in the breadcrumb trail
    // then the user MIGHT have navigated down into a subcategory.
    if (is_object($current_category) && property_exists($current_category, 'parent') && array_key_exists($current_category->parent, $breadcrumbs)) {
      // Go to the end of the breadcrumb array.
      end($breadcrumbs);
      // If the last category id in the breadcrumb equals the parent category id of
      // the current category the the user HAS navigated down into a subcategory.
      if (key($breadcrumbs) == $current_category->parent) {
        // Add the current category to the breadcrumb.
        $breadcrumbs[$current_category->id] = $current_category->name;
      }
    }
    // Reset the breadcrumb array so that it can be rendered in order.
    reset($breadcrumbs);
    // Create a container for the breadcrumb.
    $form['breadcrumb-container'] = [
      '#type' => 'container',
      // Custom element property to store breadcrumbs array.
      // This is fetched from the form state every time the form is rebuilt
      // due to navigating between categories.
      '#breadcrumbs' => $breadcrumbs,
      '#attributes' => [
        'class' => ['breadcrumb acquiadam-browser-breadcrumb-container'],
      ],
    ];
    // Add the breadcrumb buttons to the form.
    foreach ($breadcrumbs as $category_id => $category_name) {
      $form['breadcrumb-container'][$category_id] = [
        '#type' => 'button',
        '#value' => $category_name,
        '#name' => 'acquiadam_category',
        '#acquiadam_category_id' => $category_id,
        '#acquiadam_parent_category_id' => $category_name,
        '#prefix' => '<li>',
        '#suffix' => '</li>',
        '#attributes' => [
          'class' => ['acquiadam-browser-breadcrumb'],
        ],
      ];
    }
    return $form;
  }

  /**
   * Format display of one asset in media browser.
   *
   * @return string
   *   Element HTML markup.
   *
   * @var \Drupal\acquiadam\Entity\Asset $acquiadamAsset
   */
  public function layoutMediaEntity(Asset $acquiadamAsset) {
    $modulePath = $this->moduleHandler->getModule('acquiadam')->getPath();

    $assetName = $acquiadamAsset->filename;
    if (!empty($acquiadamAsset->thumbnails)) {
      $thumbnail = '<div class="acquiadam-asset-thumb"><img src="' . $acquiadamAsset->thumbnails->{"300px"}->url . '" alt="' . $assetName . '" /></div>';
    }
    else {
      $thumbnail = '<span class="acquiadam-browser-empty">No preview available.</span>';
    }
    $element = '<div class="acquiadam-asset-checkbox">' . $thumbnail . '<div class="acquiadam-asset-details"><a href="/acquiadam/asset/' . $acquiadamAsset->id . '" class="use-ajax" data-dialog-type="modal"><img src="/' . $modulePath . '/img/ext-link.png" alt="category link" class="acquiadam-asset-browser-icon" /></a><p class="acquiadam-asset-filename">' . $assetName . '</p></div></div>';
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Create a custom pager.
   */
  public function getPager(category $current_category, $page, $num_per_page) {
    // Add container for pager.
    $form['pager-container'] = [
      '#type' => 'container',
      // Store page number in container so it can be retrieved from form state.
      '#page' => $page,
      '#attributes' => [
        'class' => ['acquiadam-asset-browser-pager'],
      ],
    ];
    // If not on the first page.
    if ($page > 0) {
      // Add a button to go to the first page.
      $form['pager-container']['first'] = [
        '#type' => 'button',
        '#value' => '<<',
        '#name' => 'acquiadam_pager',
        '#acquiadam_page' => 0,
        '#attributes' => [
          'class' => ['page-button', 'page-first'],
        ],
      ];
      // Add a button to go to the previous page.
      $form['pager-container']['previous'] = [
        '#type' => 'button',
        '#value' => '<',
        '#name' => 'acquiadam_pager',
        '#acquiadam_page' => $page - 1,
        '#attributes' => [
          'class' => ['page-button', 'page-previous'],
        ],
      ];
    }
    // Last available page based on number of assets in category
    // divided by number of assets to show per page.
    // $last_page = floor(($current_category->numassets - 1) / $num_per_page);
    // First page to show in the pager.
    // Try to put the button for the current page in the middle by starting at
    // the current page number minus 4.
    $start_page = max(0, $page - 4);
    // Last page to show in the pager.  Don't go beyond the last available page.
    // $end_page = min($start_page + 9, $last_page);
    // Create buttons for pages from start to end.
    for ($i = $start_page; $i <= $end_page; $i++) {
      $form['pager-container']['page_' . $i] = [
        '#type' => 'button',
        '#value' => $i + 1,
        '#name' => 'acquiadam_pager',
        '#acquiadam_page' => $i,
        '#attributes' => [
          'class' => [($i == $page ? 'page-current' : ''), 'page-button'],
        ],
      ];
    }
    // If not on the last page.
    if ($end_page > $page) {
      // Add a button to go to the next page.
      $form['pager-container']['next'] = [
        '#type' => 'button',
        '#value' => '>',
        '#name' => 'acquiadam_pager',
        '#acquiadam_page' => $page + 1,
        '#attributes' => [
          'class' => ['page-button', 'page-next'],
        ],
      ];
      // Add a button to go to the last page.
      $form['pager-container']['last'] = [
        '#type' => 'button',
        '#value' => '>>',
        '#name' => 'acquiadam_pager',
        '#acquiadam_page' => $last_page,
        '#attributes' => [
          'class' => ['page-button', 'page-last'],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    // If the primary submit button was clicked to select assets.
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      // The media bundle.
      $media_bundle = $this->entityTypeManager->getStorage('media_type')
        ->load($this->configuration['media_type']);
      // Load the field definitions for this bundle.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', $media_bundle->id());
      // Load the file settings to validate against.
      $field_map = $media_bundle->getFieldMap();
      if (!isset($field_map['file'])) {
        $message = $this->t('Missing file mapping. Check your media configuration.');
        $form_state->setError($form['widget']['asset-container']['assets'], $message);
        return;
      }
      $file_extensions = $field_definitions[$field_map['file']]->getItemDefinition()
        ->getSetting('file_extensions');
      $supported_extensions = explode(',', preg_replace('/,?\s/', ',', $file_extensions));
      // The form input uses checkboxes which returns zero for unchecked assets.
      // Remove these unchecked assets.
      $assets = array_filter($form_state->getValue('assets'));
      // Fetch assets.
      $dam_assets = $this->acquiadam->getAssetMultiple($assets);
      // Get the cardinality for the media field that is being populated.
      $field_cardinality = $form_state->get([
        'entity_browser',
        'validators',
        'cardinality',
        'cardinality',
      ]);

      if (!count($assets)) {
        $form_state->setError($form['widget']['asset-container'], $this->t('Please select an asset.'));
      }

      // If the field cardinality is limited and the number of assets selected
      // is greater than the field cardinality.
      if ($field_cardinality > 0 && count($assets) > $field_cardinality) {
        // Format the error message for singular or plural
        // depending on cardinality.
        $message = $this->formatPlural($field_cardinality, 'You can not select more than 1 entity.', 'You can not select more than @count entities.');
        // Set the error message on the form.
        $form_state->setError($form['widget']['asset-container']['assets'], $message);
      }

      // If the asset's file type does not match allowed file types.
      // foreach ($dam_assets as $asset) {
      //   // $filetype = $asset->filetype;
      //   $type_is_supported = in_array($filetype, $supported_extensions);

      //   if (!$type_is_supported) {
      //     $message = $this->t('Please make another selection. The "@filetype" file type is not one of the supported file types (@supported_types).', [
      //       '@filetype' => $filetype,
      //       '@supported_types' => implode(', ', $supported_extensions),
      //     ]);
      //     // Set the error message on the form.
      //     $form_state->setError($form['widget']['asset-container']['assets'], $message);
      //   }
      // }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $assets = [];
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $assets = $this->prepareEntities($form, $form_state);
    }
    $this->selectEntities($assets, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    // Get asset id's from form state.
    $asset_ids = $form_state->getValue('current_selections', []) + array_filter($form_state->getValue('assets', []));
    // Load type information.
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')
      ->load($this->configuration['media_type']);
    // Get the source field for this type which stores the asset id.
    $source_field = $media_type->getSource()
      ->getSourceFieldDefinition($media_type)
      ->getName();
    // Query for existing entities.
    $existing_ids = $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->condition('bundle', $media_type->id())
      ->condition($source_field, $asset_ids, 'IN')
      ->execute();
    // Load the entities found.
    $entities = $this->entityTypeManager->getStorage('media')
      ->loadMultiple($existing_ids);
    // Loop through the existing entities.
    foreach ($entities as $entity) {
      // Set the asset id of the current entity.
      $asset_id = $entity->get($source_field)->value;
      // If the asset id of the entity is in the list of asset id's selected.
      if (in_array($asset_id, $asset_ids)) {
        // Remove the asset id from the input so it does not get fetched
        // and does not get created as a duplicate.
        unset($asset_ids[$asset_id]);
      }
    }
    // Fetch the assets.
    $assets = $this->acquiadam->getAssetMultiple($asset_ids);
    // Loop through the returned assets.
    foreach ($assets as $asset) {
      // Initialize entity values.
      $entity_values = [
        'bundle' => $media_type->id(),
        // This should be the current user id.
        'uid' => $this->user->id(),
        // This should be the current language code.
        'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
        // This should map the asset status to the drupal entity status.
        /**
         * @todo: find out if we can use status from Acquia Dam.
         */
        'status' => 1,
        // Set the entity name to the asset name.
        /**
         * @todo: Once we use the `metadata info` in Asset we require to replace this with Display Name here.
         */
        'name' => $asset->filename,
        // Set the chosen source field for this entity to the asset id.
        $source_field => $asset->id,
      ];
      // Create a new entity to represent the asset.
      $entity = $this->entityTypeManager->getStorage('media')
        ->create($entity_values);
      // Save the entity.
      $entity->save();
      // Reload the entity to make sure we have everything populated properly.
      $entity = $this->entityTypeManager->getStorage('media')
        ->load($entity->id());
      // Add the new entity to the array of returned entities.
      $entities[] = $entity;
    }
    // Return the entities.
    return $entities;
  }

  /**
   * {@inheritDoc}
   */
  public function getCategoryFormElements($category, $modulePath, &$form) {
    $form['asset-container']['categories'][$category->name] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['acquiadam-browser-category-link'],
        'style' => 'background-image:url("/' . $modulePath . '/img/category.png")',
      ],
    ];
    $form['asset-container']['categories'][$category->name][$category->id] = [
      '#type' => 'button',
      '#value' => $category->name,
      '#name' => 'acquiadam_category',
      '#acquiadam_category_id' => $category->id,
      '#acquiadam_subcategory_count' => !empty($category->categories) ? 1 : 0,
      '#attributes' => [
        'class' => ['acquiadam-category-link-button'],
      ],
    ];
    $form['asset-container']['categories'][$category->name]['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $category->name,
    ];
}

}
