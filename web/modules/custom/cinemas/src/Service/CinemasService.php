<?php

namespace Drupal\cinemas\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\key\KeyRepository;
use GuzzleHttp\Client;

class CinemasService{
  /**
   * Configuration Factory.
   *
   * @var ConfigFactory
   */
  protected $configFactory;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var Client
   */
  protected $httpClient;

  /**
   * The Key repository to store tokens.
   *
   * @var KeyRepository
   */
  protected $keyRepository;

  /**
   * A Token instance.
   *
   * @var Drupal\token
   */
  protected $token;

  /**
   * SettingService.
   *
   * @var Drupal\cinemas\Service\SettingService
   */
  protected $settingService;

  /**
   * Construct an Cinema Service.
   *
   * @param GuzzleHttp\Client $httpClientclient
   *   Cache backend.
   * @param Drupal\Core\Config\ConfigFactory $configFactory
   *   Config Factory.
   * @param Drupal\key\KeyRepository $keyRepository
   *   Key Repository.
   * @param Drupal\cinemas\Service\SettingService $settingService
   *   Setting Service.
   */
  public function __construct(Client $httpClient, ConfigFactory $configFactory, KeyRepository $keyRepository, SettingService $settingService)
  {
    $this->httpClient = $httpClient;
    $this->configFactory = $configFactory->getEditable('cinemas.settings');
    $this->keyRepository = $keyRepository;
    $this->settingService = $settingService;
    if (time() > $this->settingService->getTokenExpiration()){
      $this->token = $this->settingService->authenticate($this->configFactory->get('url_base'), $this->configFactory->get('key_select'));
    }
    $this->token = $this->settingService->getToken($this->configFactory->get('key_select'));
    //$this->token = $settingService->getToken($this->configFactory->get('key_select'));
  }

  public function responseByMovieId($id){
    $url_base = $this->configFactory->get('url_base');
    $url = $url_base.'/theatres/';
    $response = $this->httpClient->request('GET', $url, [
      'auth' => [$this->token],
    ]);
    $data = json_decode($response->getBody());
    $result = [];
    foreach ($data as $item){
      if (in_array($id, $item->movie_id)){
        $result[] = $item;
      }
    }
    return $result;
  }

  public function getCinema(){
    $url_base = $this->configFactory->get('url_base');
    $url = $url_base.'/theatres/a93d74f1-307f-443b-bd99-88d709bbace7';
    $response = $this->httpClient->request('GET', $url, ['auth' => [$this->token]]);
    return json_decode($response->getBody());
  }
}
