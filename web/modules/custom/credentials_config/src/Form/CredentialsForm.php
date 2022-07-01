<?php
namespace Drupal\credentials_config\Form;


use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ball form.
 *
 * @property \Drupal\credentials_config\CredentialsInterface $entity
 */

class CredentialsForm extends EntityForm{
  /**
   * Constructs a Credentials object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function form(array $form, FormStateInterface $form_state){
    $form = parent::form($form, $form_state);

    $credentials = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $credentials->label(),
      '#description' => $this->t('The name for this credentials configuration.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $credentials->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$credentials->isNew(),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#maxlength' => 255,
      '#default_value' => $credentials->getUrl(),
      '#description' => $this->t('The URL for this credentials configuration.'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#maxlength' => 255,
      '#default_value' => $credentials->getAPIKey(),
      '#description' => $this->t('The API key for this credentials configuration.'),
      '#required' => TRUE,
    ];

    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#maxlength' => 255,
      '#default_value' => $credentials->getAPISecret(),
      '#description' => $this->t('The API secret for this credentials configuration.'),
      '#required' => TRUE,
    ];

    $form['cancel'] = [
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#attributes' => array('onClick' => 'history.go(-1); event.preventDefault();'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $credentials = $this->entity;
    $status = $credentials->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The %label credentials configuration created.', [
        '%label' => $credentials->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label credentials configuration updated.', [
        '%label' => $credentials->label(),
      ]));
    }

    $form_state->setRedirect('entity.credentials.collection');
  }

  /**
   * Helper function to check whether a credentials configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('credentials')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $entity = $this->entity;
    if (!$entity->isNew()){
      $actions['preview'] = [
        '#type' => 'link',
        '#title' => $this->t('Preview'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
        '#url' => $entity->toUrl('preview-page',[$entity->id()])
      ];
    }
    return $actions;
  }

}
