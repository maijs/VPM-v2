<?php

namespace Drupal\latvia_auth;

/**
 * Interface AuthenticationServiceInterface.
 *
 * The interface for the AuthenticationService.
 * Defines methods that should be used when the interface is implemented.
 *
 * @package Drupal\latvia_auth
 */
interface AuthenticationServiceInterface {

  /**
   * Processes login request.
   *
   * Gets the user data from the assertion request and calls other functions
   * accordingly.
   *
   * @return mixed
   *   Returns the user data needed for authentification.
   *
   * @see self::processLogin()
   */
  public function processLoginRequest();

  /**
   * Authenticates the user in the system.
   *
   * @param mixed $data
   *   The data required for authentication in the system.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|false
   *   Redirect response which indicates that the user has been logged in or
   *   FALSE if login failed.
   *
   * @see self::processLoginRequest()
   */
  public function processLogin($data);

}
