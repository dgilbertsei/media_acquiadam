<?php

namespace Drupal\media_acquiadam\Plugin\EntityBrowser\Widget;

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
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaSourceManager;
use Drupal\media_acquiadam\AcquiadamAuthService;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\media_acquiadam\Entity\Asset;
use Drupal\media_acquiadam\Entity\Category;
use Drupal\media_acquiadam\Form\AcquiadamConfig;
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
   * @var \Drupal\media_acquiadam\AcquiadamInterface
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
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'), $container->get('entity_type.manager'), $container->get('entity_field.manager'), $container->get('plugin.manager.entity_browser.widget_validation'), $container->get('media_acquiadam.acquiadam_user_creds'), $container->get('current_user'), $container->get('language_manager'), $container->get('module_handler'), $container->get('plugin.manager.media.source'), $container->get('user.data'), $container->get('request_stack'), $container->get('config.factory'));
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
    // error message with invitation to authenticate via user edit form.
    $auth = $this->acquiadam->getAuthState();
    if (empty($auth['valid_token'])) {
      $return_link = Url::fromRoute('media_acquiadam.user_auth', ['uid' => $this->user->id()], ['absolute' => TRUE])->toString();
      $auth_url = AcquiadamAuthService::generateAuthUrl($return_link);
      if ($auth_url) {
        $auth_link = Url::fromUri($auth_url, ['attributes' => ['target' => '_blank']]);
        $message = $this->t('You are not authenticated. Please %authenticate to browse Acquia DAM assets. after successful authentication close this modal and reopen it to browse Acquia DAM assets.', [
          '%authenticate' => Link::fromTextAndUrl("Authenticate", $auth_link)->toString(),
        ]);
      }
      else {
        // If Acquia Dam module is not configured yet, display an error message
        // to configure the module first.
        $message = $this->t('Acquia DAM module is not configured yet. Please contact your administrator to do so.');
        // If user has permission then error message will include config form
        // link.
        if ($this->user->hasPermission('administer site configuration')) {
          $message = $this->t('Acquia DAM module is not configured yet. Please %config it to start using Acquia DAM assets.', [
            '%config' => Link::createFromRoute($this->t('configure'), 'media_acquiadam.config', [], ['attributes' => ['target' => '_blank']])->toString(),
          ]);
        }
      }

      $form['message'] = [
        '#theme' => 'asset_browser_message',
        '#message' => $message,
        '#attached' => [
          'library' => [
            'media_acquiadam/asset_browser',
          ],
        ],
      ];
      return $form;
    }

    // Start by inheriting parent form.
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    $config = $this->config->get('media_acquiadam.settings');

    // Attach the modal library.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    // This form is submitted and rebuilt when a category is clicked.
    // The triggering element identifies which category button was clicked.
    $trigger_elem = $form_state->getTriggeringElement();

    // Initialize current_category.
    $current_category = new Category();
    // Default current category name to NULL which will act as root category.
    $current_category->name = NULL;
    $current_category->parts = [];
    // Default current page to first page.
    $page = 0;
    // Number of assets to show per page.
    $num_per_page = $config->get('num_assets_per_page') ?? AcquiadamConfig::NUM_ASSETS_PER_PAGE;
    // Total number of assets.
    $total_asset = 0;
    // If the form state contains the widget AND the reset button hadn't been
    // clicked then pull values for the current form state.
    if (isset($form_state->getCompleteForm()['widget']) && isset($trigger_elem) && $trigger_elem['#name'] != 'filter_sort_reset') {
      // Assign $widget for convenience.
      $widget = $form_state->getCompleteForm()['widget'];
      if (isset($widget['pager-container']) && is_numeric($widget['pager-container']['#page'])) {
        // Set the page number to the value stored in the form state.
        $page = intval($widget['pager-container']['#page']);
      }
      if (isset($widget['asset-container']) && isset($widget['asset-container']['#acquiadam_category'])) {
        // Set current category to the value stored in the form state.
        $current_category->name = $widget['asset-container']['#acquiadam_category']['name'];
        $current_category->parts = $widget['asset-container']['#acquiadam_category']['parts'];
        $current_category->links = $widget['asset-container']['#acquiadam_category']['links'];
        $current_category->categories = $widget['asset-container']['#acquiadam_category']['categories'];
      }
      if ($form_state->getValue('assets')) {
        $current_selections = $form_state->getValue('current_selections', []) + array_filter($form_state->getValue('assets', []));
        $form['current_selections'] = [
          '#type' => 'value',
          '#value' => $current_selections,
        ];
      }
    }

    // Use "listing" for category view or "search" for search view.
    $page_type = "listing";

    // If the form has been submitted.
    if (isset($trigger_elem)) {
      // If a category button has been clicked.
      if ($trigger_elem['#name'] === 'acquiadam_category') {
        // Update the required information of selected category.
        $current_category->name = $trigger_elem['#acquiadam_category']['name'];
        $current_category->parts = $trigger_elem['#acquiadam_category']['parts'];
        $current_category->links = $trigger_elem['#acquiadam_category']['links'];
        // Reset page to zero if we have navigated to a new category.
        $page = 0;
      }
      // Set the parts value from the breadcrumb button, so selected category
      // can be loaded.
      if ($trigger_elem['#name'] === 'breadcrumb') {
        $current_category->name = $trigger_elem["#category_name"];
        $current_category->parts = $trigger_elem["#parts"];
      }
      // If a pager button has been clicked.
      if ($trigger_elem['#name'] === 'acquiadam_pager') {
        $page_type = $trigger_elem['#page_type'];
        $current_category->name = $trigger_elem['#current_category']->name ?? NULL;
        $current_category->parts = $trigger_elem['#current_category']->parts ?? [];
        // Set the current category id to the id of the category, was clicked.
        $page = intval($trigger_elem['#acquiadam_page']);
      }
      // If the filter/sort submit button has been clicked.
      if ($trigger_elem['#name'] === 'filter_sort_submit') {
        $page_type = "search";
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
    $filter_type = $form_state->getValue('format_type') ? 'ft:' . $form_state->getValue('format_type') : '';
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
    // Load search results if filter is clicked.
    if ($page_type == "search") {
      $search_results = $this->acquiadam->searchAssets($params);
      $items = isset($search_results['assets']) ? $search_results['assets'] : [];
      // Total number of assets.
      $total_asset = isset($search_results['total_count']) ? $search_results['total_count'] : 0;
    }
    // Load categories data.
    else {
      $category_name = '';
      $categories = $this->acquiadam->getCategoryData($current_category);
      // Total number of categories.
      $total_asset = $total_category = count($categories);
      // Update offset value if category contains both sub category and asset.
      if ($total_category <= $offset) {
        $params['offset'] = $offset - $total_category;
      }
      // Update Limit value if sub categories number is less than the number
      // of items per page.
      if ($total_category < $num_per_page) {
        $params['limit'] = $num_per_page - $total_category;
      }
      // Reset limit value after all the categories are already displayed
      // in previous page.
      if ($offset > $total_category) {
        $params['limit'] = $num_per_page;
      }
      if ($current_category->name) {
        $category_name = $current_category->name;
      }
      $category_assets = $this->acquiadam->getAssetsByCategory($category_name, $params);
      if ($total_category == 0 || $total_category <= $offset || $total_category < $num_per_page) {
        $items = isset($category_assets['assets']) ? $category_assets['assets'] : [];
      }
      // Total asset conatins both asset and subcategory(if any).
      $total_asset += isset($category_assets['total_count']) ? $category_assets['total_count'] : 0;
    }

    // Add the filter and sort options to the form.
    $form += $this->getFilterSort();
    // Add the breadcrumb to the form.
    $form += $this->getBreadcrumb($current_category);
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
    $modulePath = $this->moduleHandler->getModule('media_acquiadam')->getPath();

    // If no search terms, display Acquia DAM Categories.
    if (!empty($categories) && ($offset < count($categories))) {
      $initial = 0;
      if ($page != 0) {
        $offset = $num_per_page * $page;
        $categories = array_slice($categories, $offset);
      }
      // Add category buttons to form.
      foreach ($categories as $category) {
        if ($initial < $num_per_page) {
          $this->getCategoryFormElements($category, $modulePath, $form);
          $initial++;
        }
      }
    }
    // Assets are rendered as #options for a checkboxes element.
    // Start with an empty array.
    $assets = [];
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
          'media_acquiadam/asset_browser',
        ],
      ],
    ];
    // If the number of assets in the current category is greater than
    // the number of assets to show per page.
    if ($total_asset > $num_per_page) {
      // Add the pager to the form.
      $form['actions'] += $this->getPager($total_asset, $page, $num_per_page, $page_type, $current_category);
    }
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
        'filename' => $this->t('File name'),
        'size' => $this->t('File size'),
        'created_date' => $this->t('Date created'),
        'last_update_date' => $this->t('Date modified'),
      ],
      '#default_value' => 'created_date',
    ];
    // Add dropdown for sort direction.
    $form['filter-sort-container']['sortdir'] = [
      '#type' => 'select',
      '#title' => 'Sort direction',
      '#options' => [
        'asc' => $this->t('Ascending'),
        'desc' => $this->t('Descending'),
      ],
      '#default_value' => 'asc',
    ];
    // Add dropdown for filtering on asset type.
    $form['filter-sort-container']['format_type'] = [
      '#type' => 'select',
      '#title' => 'File format',
      '#options' => Asset::getFileFormats(),
      '#default_value' => 0,
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
  public function getBreadcrumb(Category $category) {

    // Create a container for the breadcrumb.
    $form['breadcrumb-container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['breadcrumb acquiadam-browser-breadcrumb-container'],
      ],
    ];
    // Placeholder to keep parts information for breadcrumbs.
    $level = [];
    // Add the home breadcrumb buttons to the form.
    $form['breadcrumb-container'][0] = [
      '#type' => 'button',
      '#value' => "Home",
      '#name' => 'breadcrumb',
      '#category_name' => NULL,
      '#parts' => $level,
      '#prefix' => '<li>',
      '#suffix' => '</li>',
      '#attributes' => [
        'class' => ['acquiadam-browser-breadcrumb'],
      ],
    ];
    // Add the breadcrumb buttons to the form.
    foreach ($category->parts as $key => $category_name) {
      $level[] = $category_name;
      // Increment it so doesn't overwrite the home.
      $key++;
      $form['breadcrumb-container'][$key] = [
        '#type' => 'button',
        '#value' => $category_name,
        '#category_name' => $category_name,
        '#name' => 'breadcrumb',
        '#parts' => $level,
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
   * @var \Drupal\media_acquiadam\Entity\Asset $acquiadamAsset
   */
  public function layoutMediaEntity(Asset $acquiadamAsset) {
    $modulePath = $this->moduleHandler->getModule('media_acquiadam')->getPath();

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
  public function getPager($total_count, $page, $num_per_page, $page_type = "listing", Category $category = NULL) {
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
        '#page_type' => $page_type,
        '#current_category' => $category,
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
        '#page_type' => $page_type,
        '#acquiadam_page' => $page - 1,
        '#current_category' => $category,
        '#attributes' => [
          'class' => ['page-button', 'page-previous'],
        ],
      ];
    }
    // Last available page based on number of assets in category
    // divided by number of assets to show per page.
    $last_page = floor(($total_count - 1) / $num_per_page);
    // First page to show in the pager.
    // Try to put the button for the current page in the middle by starting at
    // the current page number minus 4.
    $start_page = max(0, $page - 4);
    // Last page to show in the pager.  Don't go beyond the last available page.
    $end_page = min($start_page + 9, $last_page);
    // Create buttons for pages from start to end.
    for ($i = $start_page; $i <= $end_page; $i++) {
      $form['pager-container']['page_' . $i] = [
        '#type' => 'button',
        '#value' => $i + 1,
        '#name' => 'acquiadam_pager',
        '#page_type' => $page_type,
        '#acquiadam_page' => $i,
        '#current_category' => $category,
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
        '#current_category' => $category,
        '#page_type' => $page_type,
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
        '#current_category' => $category,
        '#acquiadam_page' => $last_page,
        '#page_type' => $page_type,
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
      $media_bundle = $this->entityTypeManager->getStorage('media_type')
        ->load($this->configuration['media_type']);

      // Load the file settings to validate against.
      $field_map = $media_bundle->getFieldMap();
      if (!isset($field_map['file'])) {
        $message = $this->t('Missing file mapping. Check your media configuration.');
        $form_state->setError($form['widget']['asset-container']['assets'], $message);
        return;
      }

      // The form input uses checkboxes which returns zero for unchecked assets.
      // Remove these unchecked assets.
      $assets = array_filter($form_state->getValue('assets'));

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
        $message = $this->formatPlural($field_cardinality, 'You can not select more than 1 entity.', 'You can not select more than @count entities.');
        $form_state->setError($form['widget']['asset-container']['assets'], $message);
      }

      // Get information about the file field used to handle the asset file.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', $media_bundle->id());
      $field_definition = $field_definitions[$field_map['file']]->getItemDefinition();

      // Invoke the API to get all the information about the selected assets.
      $dam_assets = $this->acquiadam->getAssetMultiple($assets);

      // If the media is only referencing images, we only validate that
      // referenced assets are images. We don't check the extension as we are
      // downloading the png version anyway.
      if (is_a($field_definition->getClass(), ImageItem::class, TRUE)) {
        foreach ($dam_assets as $asset) {
          if ($asset->file_properties->format_type !== 'image') {
            $message = $this->t('Please make another selection. Only images are supported.');
            $form_state->setError($form['widget']['asset-container']['assets'], $message);
          }
        }
      }
      else {
        // Get the list of allowed extensions for this media bundle.
        $file_extensions = $field_definition->getSetting('file_extensions');
        $supported_extensions = explode(',', preg_replace('/,?\s/', ',', $file_extensions));

        // Browse the selected assets to validate the extensions are allowed.
        foreach ($dam_assets as $asset) {
          $filetype = pathinfo($asset->filename, PATHINFO_EXTENSION);
          $type_is_supported = in_array($filetype, $supported_extensions);

          if (!$type_is_supported) {
            $message = $this->t('Please make another selection. The "@filetype" file type is not one of the supported file types (@supported_types).', [
              '@filetype' => $filetype,
              '@supported_types' => implode(', ', $supported_extensions),
            ]);
            $form_state->setError($form['widget']['asset-container']['assets'], $message);
          }
        }
      }
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
    $entities = $this->entityTypeManager->getStorage('media')
      ->loadMultiple($existing_ids);

    // We remove the existing media from the asset_ids array, so they do not
    // get fetched and created as duplicates.
    foreach ($entities as $entity) {
      $asset_id = $entity->get($source_field)->value;

      if (in_array($asset_id, $asset_ids)) {
        unset($asset_ids[$asset_id]);
      }
    }

    $assets = $this->acquiadam->getAssetMultiple($asset_ids);

    foreach ($assets as $asset) {
      $entity_values = [
        'bundle' => $media_type->id(),
        'uid' => $this->user->id(),
        'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
        // @todo Find out if we can use status from Acquia Dam.
        'status' => 1,
        'name' => $asset->filename,
        $source_field => $asset->id,
        'created' => strtotime($asset->created_date),
        'changed' => strtotime($asset->last_update_date),
      ];

      // Create a new entity to represent the asset.
      $entity = $this->entityTypeManager->getStorage('media')
        ->create($entity_values);
      $entity->save();

      // Reload the entity to make sure we have everything populated properly.
      $entity = $this->entityTypeManager->getStorage('media')
        ->load($entity->id());

      // Add the new entity to the array of returned entities.
      $entities[] = $entity;
    }

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
      '#acquiadam_category' => $category->jsonSerialize(),
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
