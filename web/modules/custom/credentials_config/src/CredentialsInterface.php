<?php

namespace Drupal\credentials_config;

use Drupal\Core\Config\Entity\ConfigEntityInterface;


/**
 * Provides an interface defining a credentials configuration entity.
 */

interface CredentialsInterface extends ConfigEntityInterface{
  public function getLabel();
  public function getUrl();
  public function getAPIKey();
  public function getAPISecret();
}
