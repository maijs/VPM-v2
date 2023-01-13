<?php

namespace Drupal\latvia_auth;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use OneLogin\Saml2\Auth;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * AuthenticationService Class.
 *
 * This class takes care of logging the user in and/or creating one when not
 * present yet. The difference with the SAMLAuthenticatorFactory, is that that
 * class instantiates the OneLogin_Saml2_Auth library with certain settings,
 * while this class uses that instance to log the user in.
 *
 * @package Drupal\latvia_auth
 */
class AuthenticationService implements AuthenticationServiceInterface {

  /**
   * The variable that holds an instance of the OneLogin_Saml2_Auth library.
   *
   * @var \OneLogin\Saml2\Auth
   */
  private $oneLoginSaml2Auth;

  /**
   * The variable that holds an instance of the EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Encryption service definition.
   *
   * @var \Drupal\latvia_auth\Cryptor
   */
  protected $cryptor;

  /**
   * Required assertion attributes.
   *
   * @var string[]
   */
  protected $requiredAttributes = [
    'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier',
  ];

  /**
   * The Saml attribute which defines user identity in Drupal.
   *
   * @var string
   */
  protected $identityAttribute = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier';

  /**
   * The user entity field name which is used for user authentication.
   *
   * @var string
   */
  protected $identityUserFieldName = 'field_user_personal_code';

  /**
   * AuthenticationService constructor.
   *
   * @param \OneLogin\Saml2\Auth $one_login_saml_2_auth
   *   Reference to OneLogin_Saml2_Auth.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Reference to EntityTypeManagerInterface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger Factory.
   * @param \Drupal\latvia_auth\Cryptor $cryptor
   */
  public function __construct(Auth $one_login_saml_2_auth, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, LoggerInterface $logger, Cryptor $cryptor) {
    $this->oneLoginSaml2Auth = $one_login_saml_2_auth;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->cryptor = $cryptor;
  }

  /**
   * {@inheritdoc}
   *
   * This function returns the required attributes from the Saml login request.
   */
  public function processLoginRequest() {
    try {
      // Validate login request values.
      $this->validateLoginRequest();

      $result = [];

      foreach ($this->requiredAttributes as $attribute) {
        $result[$attribute] = $this->oneLoginSaml2Auth->getAttribute($attribute)[0] ?? NULL;
      }

      return $result;
    }
    catch (\Exception $e) {
      watchdog_exception('latvia_auth', $e);
    }

    return FALSE;
  }

  /**
   * Validates the Saml response.
   *
   * @throws \Exception
   */
  protected function validateLoginRequest() {
    foreach ($this->requiredAttributes as $attribute) {
      if (!isset($this->oneLoginSaml2Auth->getAttribute($attribute)[0])) {
        throw new \Exception(sprintf('An attribute "%s" cannot be found in the Saml response.', $attribute));
      }
    }
  }

  /**
   * Authenticates the user by personal identifier.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|false
   *   The user ID if successfully logged in or NULL.
   */
  public function processLogin($data) {
    try {
      $this->validateLoginData($data);

      if ($account = $this->load($this->getIdentityAttributeValue($data))) {
        // Finalize login.
        $this->userLoginFinalize($account);

        return $this->prepareResponse('entity.user.canonical', ['user' => $account->id()]);
      }
    } catch (\Exception $e) {
      watchdog_exception('latvia_auth', $e);
    }

    return FALSE;
  }

  /**
   * Validates user data array.
   *
   * @param $data
   *   User data array.
   *
   * @throws \Exception
   */
  protected function validateLoginData($data) {
    foreach ($this->requiredAttributes as $attribute) {
      if (!isset($data[$attribute])) {
        throw new \Exception(sprintf('An attribute "%s" could not be found in the provided data.', $attribute));
      }
    }
  }

  /**
   * Returns identity attribute value.
   *
   * @param mixed $data
   *   User data array.
   *
   * @return string|null
   */
  protected function getIdentityAttributeValue($data) {
    return $data[$this->identityAttribute] ?? NULL;
  }

  /**
   * Load a Drupal user based on an external identifier.
   *
   * @param string $identifier
   *   The unique, external authentication name provided by authentication
   *   provider.
   *
   * @return \Drupal\user\UserInterface|false
   *   The loaded Drupal user.
   */
  public function load($identifier) {
    try {
      // Hash the identifier.
      $identifier = $this->cryptor->hashString($identifier);
      // Get users by given identifier.
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties([$this->identityUserFieldName => $identifier]);

      if ($users && count($users) == 1) {
        return reset($users);
      }

      throw new \Exception('No user found with given user identifier.');
    }
    catch (\Exception $e) {
      watchdog_exception('latvia_auth', $e);
    }

    return FALSE;
  }

  /**
   * Finalize logging in the external user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user to finalize login for.
   *
   * @return \Drupal\user\UserInterface
   *   The logged in Drupal user.
   */
  protected function userLoginFinalize(UserInterface $account) {
    // Finalize user login.
    user_login_finalize($account);

    // Execute additional commands after login.
    $this->postUserLoginFinalize($account);

    // Log the event.
    $this->logger->notice('User %name is logged in (via latvija.lv).', ['%name' => $account->getAccountName()]);

    return $account;
  }

  /**
   * Run additional steps after user login finalization.
   *
   * @return \Drupal\user\UserInterface
   *   The logged in Drupal user.
   *
   * @return void
   */
  protected function postUserLoginFinalize(UserInterface $account) {
    // Set the "logged_in" flag.
    \Drupal::service('user.data')->set('latvia_auth', $account->id(), 'logged_in', TRUE);
  }

  /**
   * Encrypt user data.
   *
   * @param array $user_data
   *
   * @return string
   */
  public function encryptUserData(array $user_data) {
    return $this->cryptor->encrypt(json_encode($user_data));
  }

  /**
   * Encrypt passed user data.
   *
   * @param string $user_data
   *
   * @return array|null
   */
  public function decryptUserData($user_data) {
    $user_data = $this->cryptor->decrypt($user_data);

    return (array) json_decode($user_data);
  }

  /**
   * Prepares redirect response.
   *
   * @param $route_name
   *   The route name.
   * @param array $route_parameters
   *   The route parameters.
   * @param array $options
   *   The route options.
   * @param $status
   *   The status code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  protected function prepareResponse($route_name, array $route_parameters = [], array $options = [], $status = 302) {
    $options['absolute'] = TRUE;
    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

}
