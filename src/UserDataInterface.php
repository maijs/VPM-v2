<?php

namespace Drupal\latvia_auth;

/**
 * User data interface.
 */
interface UserDataInterface {

  /**
   * Returns user data as array.
   *
   * @return array
   */
  public function asArray();

  /**
   * Returns national identification number.
   *
   * @return string
   */
  public function getNationalIdentificationNumber();

  /**
   * Returns given name.
   *
   * @return string
   */
  public function getGivenName();

  /**
   * Returns surname.
   *
   * @return string
   */
  public function getSurname();

}
