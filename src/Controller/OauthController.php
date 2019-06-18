<?php

namespace Drupal\media_acquiadam\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\media_acquiadam\OauthInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for acquiadam routes.
 */
class OauthController extends ControllerBase {

  protected $webdamApiBase = "https://apiv2.webdamdb.com";

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_acquiadam.oauth'),
      $container->get('request_stack'),
      $container->get('user.data'),
      $container->get('current_user'),
      $container->get('date.formatter')
    );
  }

  /**
   * The media_acquiadam oauth service.
   *
   * @var \Drupal\media_acquiadam\OauthInterface
   */
  protected $oauth;

  /**
   * The current request object.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * UserData interface to handle storage of tokens.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Drupal Date Formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * AcquiadamController constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(OauthInterface $oauth, RequestStack $request_stack, UserDataInterface $user_data, AccountProxyInterface $currentUser, DateFormatterInterface $dateFormatter) {
    $this->oauth = $oauth;
    $this->request = $request_stack->getCurrentRequest();
    $this->userData = $user_data;
    $this->currentUser = $currentUser;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Builds the auth page for a given user.
   *
   * Route: /user/{$user}/acquiadam.
   *
   * @param \Drupal\user\UserInterface $user
   *   The User object.
   *
   * @return array
   *   Array with HTML markup message.
   */
  public function authPage(UserInterface $user) {
    // Users cannot access other users' auth forms.
    if ($user->id() !== $this->currentUser()->id()) {
      throw new NotFoundHttpException();
    }

    $settings_not_set_message = '';

    /** @var \Drupal\Core\Config\Config $media_acquiadam_settings */
    $media_acquiadam_settings = $this->config('media_acquiadam.settings');
    if (!$media_acquiadam_settings->get('username') || !$media_acquiadam_settings->get('client_id')) {
      $settings_not_set_message = $this->getLinkGenerator()->generate($this->t('Please enter credentials for authentication'), Url::fromRoute('media_acquiadam.config', [], [
        'query' => [
          'destination' => "/user/{$this->currentUser->id()}/acquiadam",
        ],
      ]));
    }

    $access_token = $this->userData->get('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token');
    $refresh_token = $this->userData->get('media_acquiadam', $this->currentUser->id(), 'acquiadam_refresh_token');
    $access_token_expiration = $this->userData->get('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token_expiration');

    $is_expired = empty($access_token) || $access_token_expiration <= time();

    if (!$is_expired || ($is_expired && !empty($refresh_token))) {
      return [
        [
          '#markup' => '<p>' . $this->t('You are authenticated with Acquia DAM.') . '</p>',
        ],
        [
          '#markup' => '<p>' . $this->t('Your authentication expires on @date.', [
              '@date' => $this->dateFormatter->format($access_token_expiration),
            ]) . '</p>',
        ],
        [
          '#markup' => $settings_not_set_message ?: $this->getLinkGenerator()->generate('Reauthenticate', Url::fromRoute('media_acquiadam.auth_start', ['auth_finish_redirect' => "/user/{$this->currentUser->id()}/acquiadam"])),
        ],
      ];
    }
    else {
      $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token');
      $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token_expiration');
      $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_refresh_token');

      return [
        [
          '#markup' => '<p>' . $this->t('You are not authenticated with Acquia DAM.') . '</p>',
        ],
        [
          '#markup' => $settings_not_set_message ?: $this->getLinkGenerator()->generate('Authenticate', Url::fromRoute('media_acquiadam.auth_start', ['auth_finish_redirect' => "/user/{$this->currentUser->id()}/acquiadam"])),
        ],
      ];
    }
  }

  /**
   * Redirects the user to the auth url.
   *
   * Route: /acquiadam/authStart.
   */
  public function authStart() {
    $authFinishRedirect = $this->request->query->get('auth_finish_redirect');
    $this->oauth->setAuthFinishRedirect($authFinishRedirect);
    return new TrustedRedirectResponse($this->oauth->getAuthLink());
  }

  /**
   * Finish the authentication process.
   *
   * Route: /acquiadam/authFinish.
   */
  public function authFinish() {
    $authFinishRedirect = $this->request->query->get('auth_finish_redirect');
    if ($original_path = $this->request->query->get('original_path', FALSE)) {
      $authFinishRedirect .= '&original_path=' . $original_path;
    }
    $this->oauth->setAuthFinishRedirect($authFinishRedirect);
    if (!$this->oauth->authRequestStateIsValid($this->request->get('state'))) {
      throw new AccessDeniedHttpException();
    }

    $access_token = $this->oauth->getAccessToken($this->request->get('code'));

    $this->userData->set('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token', $access_token['access_token']);
    $this->userData->set('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token_expiration', $access_token['expire_time']);
    $this->userData->set('media_acquiadam', $this->currentUser->id(), 'acquiadam_refresh_token', $access_token['refresh_token']);

    return new RedirectResponse($authFinishRedirect);
  }

}
