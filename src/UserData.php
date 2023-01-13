<?php

namespace Drupal\latvia_auth;

/**
 * User data wrapper.
 */
class UserData implements UserDataInterface {

  /**
   * Creates new object from array values.
   *
   * @param array $values
   *
   * @return self
   */
  public static function createFromArray(array $values) {
    $result = new static();

    foreach ($values as $key => $value) {
      $result->set($key, $value);
    }

    return $result;
  }

  /**
   * Sets value.
   *
   * @param string $key
   * @param mixed $value
   */
  public function set($key, $value) {
    $this->{$key} = $value;
  }

  /**
   * Returns requested value.
   *
   * @param string $key
   * @param mixed|null $default
   *
   * @return mixed
   */
  public function get($key, $default = NULL) {
    return $this->{$key} ?? $default;
  }

  /**
   * Serializes the instance of this class.
   *
   * @return array
   */
  public function __serialize(): array {
    return get_object_vars($this);
  }

  /**
   * {@inheritdoc}
   */
  public function asArray() {
    return get_object_vars($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getNationalIdentificationNumber() {
    return $this->get('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier');
  }

  /**
   * {@inheritdoc}
   */
  public function getGivenName() {
    return $this->get('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname');
  }

  /**
   * {@inheritdoc}
   */
  public function getSurname() {
    return $this->get('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname');
  }

}
