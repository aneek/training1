<?php

namespace Drupal\credentials_config\Controller;

use Drupal\Core\Controller\ControllerBase;

class CredentialsController extends ControllerBase{
  public function preview($credentials)
  {
    $entity = \Drupal::entityTypeManager()->getStorage('credentials')->load($credentials);
    $items = [
      'Label: ' => $this->t('Name: '.$entity->label()),
      'Machine name: ' => $this->t('Machine name: '.$entity->id()),
      'URL: ' => $this->t('URL: '.$entity->getUrl()),
      'API Key: ' => $this->t('API Key: '.$entity->getAPIKey()),
      'API Secret: ' => $this->t('API Secret: '.$entity->getAPISecret()),
    ];
    $header = [
      'name' => t('Label'),
      'id' => t('Machine name'),
      'url' => t('URL'),
      'api_key' => t('API Key'),
      'api_secret' => t('API Secret'),
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $items,
      '#empty' => t('No content has been found.'),
      ];
    return array(
      '#theme' => 'item_list',
      '#items' => $items,
    );
  }
}
