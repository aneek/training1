<?php

namespace Drupal\transcode_profile\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;


class ConfigForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'transcode_profile_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(){
    return [
      'transcode_profile.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('transcode_profile.settings');
    //dump($this->config('transcode_profile.settings'));
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Profile Name'),
      '#default_value' => $config->get('name'),
      '#description' => $this->t('Transcode profile name'),
    ];

    $form['enable_transcoding'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable transcoding'),
      '#default_value' => $config->get('enable') ? : [],
      //'#options' => ['value' => $this->t('Enable transcoding')],
      '#description' => t('Enables video transcoding'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $name = $form_state->getValue('name');
    $enable = $form_state->getValue('enable_transcoding');

    $this->config('transcode_profile.settings')
      ->set('name', $name)
      ->set('enable', $enable)
      ->save();

    parent::submitForm($form, $form_state);
  }
}
