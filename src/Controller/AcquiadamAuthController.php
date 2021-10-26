<?php

namespace Drupal\media_acquiadam\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\media_acquiadam\AcquiadamAuthService;
use Drupal\user\UserData;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Acquia DAM Auth controller for the acquiadam module.
 */
class AcquiadamAuthController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The request stack factory service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The user data factory service.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * Constructs a new AcquiadamAuthController.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack factory.
   * @param \Drupal\user\UserData $user_data
   *   The user data factory.
   */
  public function __construct(RequestStack $request_stack, UserData $user_data) {
    $this->request = $request_stack;
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('user.data')
    );
  }

  /**
   * Menu callback from Acquia DAM to complete authorization process.
   */
  public function authenticate() {
    // Get the code returned by the Acquia DAM API endpoint, if available.
    $code = $this->request->getCurrentRequest()->query->get('code');
    $user_id = $this->request->getCurrentRequest()->query->get('uid');

    $user = $this->entityTypeManager()->getStorage('user')->load($user_id);

    if (isset($code) && !empty($user)) {
      // Save returned code to the current user profile.
      $this->handleAuthentication($code, $user);
      $this->messenger()->addStatus($this->t('Account authorized to Acquia DAM.'));
    }
    // If user does not exists.
    elseif (empty($user)) {
      $this->messenger()->addError($this->t('User does not exists.'));
    }
    // If not return an error message when authentication process returns to
    // site.
    else {
      $this->messenger()->addError($this->t('Authorization Denied. Acquia DAM did not provide an auth code.'));
    }
    return $this->redirect('user.page');
  }

  /**
   * Checks whether given account is valid and updates account information.
   *
   * @param string $auth_code
   *   The authorization code provided during user creation.
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   *
   * @todo improve function documentation block.
   */
  private function handleAuthentication(string $auth_code, UserInterface $user) {
    $response = AcquiadamAuthService::authenticate($auth_code);

    // If account is valid is and a token code has been provide, update the
    // account of the current user and set Acquia DAM credentials saving the
    // acquiadam_username and acquiadam_token values.
    if (isset($response->username) && isset($response->access_token)) {
      $account = [
        'acquiadam_username' => $response->username,
        'acquiadam_token' => $response->access_token,
      ];

      // Store acquiadam account details.
      $this
        ->userData
        ->set('media_acquiadam', $user->id(), 'account', $account);

      // Redirect back to user edit form.
      $redirect = Url::fromRoute('entity.user.edit_form', ['user' => $user->id()])->toString();
      $response = new RedirectResponse($redirect);
      $response->send();

      return;
    }
    // Else, display an user message to the user.
    else {
      $error_msg = $this->t('Authorization Failure');
      if (isset($response->error)) {
        $error_msg .= ' ' . $this->t('[@error: @description]', [
          '@error' => $response->error,
          '@description' => $response->description,
        ]);
      }

      $this->messenger()->addError($error_msg);
    }
  }

}
