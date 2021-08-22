<?php
/**
 * @file
 *
 * @todo: Replace with new service's controller.
 */
namespace Drupal\media_acquiadam\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
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

  /**
   * The base API url.
   *
   * @var string
   */
  protected $webdamApiBase = "https://apiv2.webdamdb.com";

  /**
   * The media_acquiadam oauth service.
   *
   * @var \Drupal\media_acquiadam\Oauth
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
   * Drupal Url Generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * AcquiadamController constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(OauthInterface $oauth, RequestStack $request_stack, UserDataInterface $user_data, AccountProxyInterface $currentUser, DateFormatterInterface $dateFormatter, UrlGeneratorInterface $urlGenerator) {
    $this->oauth = $oauth;
    $this->request = $request_stack->getCurrentRequest();
    $this->userData = $user_data;
    $this->currentUser = $currentUser;
    $this->dateFormatter = $dateFormatter;
    $this->urlGenerator = $urlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_acquiadam.oauth'),
      $container->get('request_stack'),
      $container->get('user.data'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('url_generator')
    );
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

    $result = [];

    /** @var \Drupal\Core\Config\Config $media_acquiadam_settings */
    $media_acquiadam_settings = $this->config('media_acquiadam.settings');
    $redirect_url = sprintf('/user/%d/acquiadam', $this->currentUser()->id());

    $access_token = $this->userData->get('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token');
    $refresh_token = $this->userData->get('media_acquiadam', $this->currentUser->id(), 'acquiadam_refresh_token');
    $access_token_expiration = $this->userData->get('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token_expiration');

    $is_expired = empty($access_token) || $access_token_expiration <= time();
    $is_authenticated = !$is_expired || ($is_expired && !empty($refresh_token));
    $is_no_credentials = !$media_acquiadam_settings->get('secret') || !$media_acquiadam_settings->get('client_id');

    if ($is_no_credentials) {

      $result[] = ['#markup' => '<p>' . $this->t('The Acquia DAM module is not fully configured.') . '</p>'];
      if ($this->currentUser()->hasPermission('administer site configuration')) {
        $config_url = $this->urlGenerator
          ->generateFromRoute('media_acquiadam.config', [], ['query' => ['destination' => $redirect_url]]);
        $result[] = ['#markup' => '<p>' . $this->t('Please <a href="@configure">configure</a> the Acquia DAM module to continue.', ['@configure' => $config_url]) . '</p>'];
      }
      else {
        $result[] = ['#markup' => '<p>' . $this->t('Please contact a site administrator to continue.') . '</p>'];
      }
    }
    elseif ($is_expired) {
      $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token');
      $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token_expiration');
      $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_refresh_token');
      $link = Link::createFromRoute('Authenticate', 'media_acquiadam.auth_start', ['auth_finish_redirect' => $redirect_url]);

      $result[] = ['#markup' => '<p>' . $this->t('You are <strong>not</strong> authenticated with Acquia DAM.') . '</p>'];
      $result[] = ['#markup' => '<p>' . $link->toString() . '</p>'];
    }
    elseif ($is_authenticated) {
      $logout_link = Link::createFromRoute('Logout from DAM', 'media_acquiadam.logout', ['auth_finish_redirect' => $redirect_url]);
      $reauthenticate_link = Link::createFromRoute('Reauthenticate', 'media_acquiadam.auth_start', ['auth_finish_redirect' => $redirect_url]);
      $result[] = ['#markup' => '<p>' . $this->t('You are authenticated with Acquia DAM.') . '</p>'];
      $result[] = [
        '#markup' => '<p>' . $this->t('Your authentication expires on @date.',
          [
            '@date' => $this->dateFormatter->format($access_token_expiration),
          ]) . '</p>',
      ];
      $result[] = ['#markup' => '<p>' . $reauthenticate_link->toString() . ' | ' . $logout_link->toString() . '</p>'];
    }

    return !empty($result) ? $result : NULL;
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
   * Redirects the user to the auth url.
   *
   * Route: /acquiadam/logout.
   */
  public function logout() {
    $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token');
    $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_access_token_expiration');
    $this->userData->delete('media_acquiadam', $this->currentUser->id(), 'acquiadam_refresh_token');

    $authFinishRedirect = $this->request->query->get('auth_finish_redirect');
    return new TrustedRedirectResponse($authFinishRedirect);
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
