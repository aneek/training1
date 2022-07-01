<?php

namespace Drupal\credentials_config\Controller;


use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Credentials.
 */

class CredentialsListBuilder extends ConfigEntityListBuilder{
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Configuration name');
    $header['id'] = $this->t('Machine name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity)
  {
    $operations = parent::getDefaultOperations($entity);
    $operations['preview'] = array(
      'title' => t('Preview'),
      'weight' => 20,
      'url' => $this->ensureDestination($entity->toUrl('preview-page',[$entity->id()])),
    );
    return $operations;
  }

}
