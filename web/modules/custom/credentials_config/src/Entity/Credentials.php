<?php

namespace Drupal\credentials_config\Entity;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\credentials_config\CredentialsInterface;

/**
 * Defines the Example entity.
 *
 * @ConfigEntityType(
 *   id = "credentials",
 *   label = @Translation("Credentials"),
 *   handlers = {
 *     "list_builder" = "Drupal\credentials_config\Controller\CredentialsListBuilder",
 *     "form" = {
 *       "add" = "Drupal\credentials_config\Form\CredentialsForm",
 *       "edit" = "Drupal\credentials_config\Form\CredentialsForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     }
 *   },
 *   config_prefix = "credentials",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "url" = "url",
 *     "api_key" = "api_key",
 *     "api_secret" = "api_secret",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "url",
 *     "api_key",
 *     "api_secret",
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/credentials",
 *     "preview-page" = "/admin/config/services/credentials/{credentials}/preview",
 *     "add-form" = "/admin/config/services/credentials/add",
 *     "edit-form" = "/admin/config/services/credentials/{credentials}",
 *     "delete-form" = "/admin/config/services/credentials/{credentials}/delete",
 *   }
 * )
 */

class Credentials extends ConfigEntityBase implements CredentialsInterface{
  /**
   * The credentials ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The credentials label.
   *
   * @var string
   */
  protected $label;

  /**
   * The credentials url.
   *
   * @var string
   */
  protected $url;

  /**
   * The credentials api key.
   *
   * @var string
   */
  protected $api_key;

  /**
   * The credentials api secret.
   *
   * @var string
   */
  protected $api_secret;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(){
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function getAPIKey(){
    return $this->api_key;
  }

  /**
   * {@inheritdoc}
   */
  public function getAPISecret(){
    return $this->api_secret;
  }
}
