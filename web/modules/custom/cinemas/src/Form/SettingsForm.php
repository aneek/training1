<?php

namespace Drupal\cinemas\Form;

use Drupal\cinemas\Service\SettingService;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\State;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase{
  /**
   * SettingService.
   *
   * @var Drupal\cinemas\Service\SettingService
   */
  protected $settingService;



  /**
   * {@inheritdoc}
   */
  public function __construct(SettingService $settingService) {
    $this->settingService = $settingService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('setting.service')
    );
  }

  public function getFormId()
  {
    return 'cinemas_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cinemas.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('cinemas.settings');
    $options = $this->settingService->getKeyList();
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      //'#placeholder' => 'API key',
      '#description' => 'The API key for this configuration.',
      '#required' => TRUE,
    ];
    $form['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $config->get('api_secret'),
      //'#placeholder' => 'API secret',
      '#description' => 'The API secret for this configuration.',
      '#required' => TRUE,
    ];
    $form['url_base'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL Base'),
      '#default_value' => $config->get('url_base'),
      //'#placeholder' => 'URL base',
      '#description' => 'For testing purpose: https://b77e3b22-8f5c-4e66-83c1-175bdc970773.mock.pstmn.io',
      '#required' => TRUE,
    ];
    $form['key_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Key'),
      '#default_value' => $config->get('key_select'),
      //'#placeholder' => 'URL base',
      '#options' => $options,
      '#description' => 'Select the key to store token',
      '#required' => TRUE,
    ];
    //dump($this->settingService->authenticate('https://ab5c974c-bf56-43be-aea4-909c523f185b.mock.pstmn.io')->access_token);
    //dump($this->settingService->getKeys());
    //dump($this->settingService->getKeyList());
//    $test = \Drupal::time()->getCurrentTime();
//    $this->state->set('token_expire', $test);
//    dump(\Drupal::state()->get('token_expire'));
    return parent::buildForm($form, $form_state);
  }

  public function saveConfig($api_key, $api_secret, $url_base, $key_select){
    $this->config('cinemas.settings')
      ->set('api_key', $api_key)
      ->set('api_secret', $api_secret)
      ->set('url_base', $url_base)
      ->set('key_select', $key_select)
      ->save();
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();
//    if(!isset($_SESSION[$values['key_select']]) || $_SESSION[$values['key_select']] <= time()){
//      $data = $this->settingService->authenticate($values['url_base']);
//      $this->settingService->setToken($data->access_token, $values['key_select']);
//      $this->settingService->setKeyName($values['key_select']);
//      $_SESSION[$values['key_select']] = time() + $data->expires_in;
//    }
    $this->settingService->authenticate($values['url_base'], $values['key_select']);
    //$this->settingService->setToken($data->access_token, $values['key_select']);
    //$this->settingService->setKeyName($values['key_select']);
    $this->saveConfig($values['api_key'], $values['api_secret'], $values['url_base'], $values['key_select']);
    parent::submitForm($form, $form_state);
  }
}
