<?php

namespace Drupal\cinemas\Service;


use Drupal\Core\State\State;
use Drupal\key\KeyRepository;
use GuzzleHttp\Client;

/**
 * The Setting service.
 *
 * @package Drupal\cinemas\Services
 */
class SettingService{
//  /**
//   * A key for cinema token.
//   *
//   * @var string
//   */
//  private static $keyName;


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
   * The Key repository to store tokens.
   *
   * @var State
   */
  protected $state;

  public function __construct(Client $httpClient, KeyRepository $keyRepository, State $state)
  {
    $this->httpClient = $httpClient;
    $this->keyRepository = $keyRepository;
    $this->state = $state;
  }

  public function authenticate($url_base, $key_name){
    $url = $url_base.'/authenticate';
    $response = $this->httpClient->request('POST', $url);
    $data = json_decode($response->getBody());
    $this->setToken($data->access_token, $key_name);
    $this->setTokenExpiration(time() + $data->expires_in);
    return $data;
  }

  public function setToken($token, $key_name){
    $key = $this->keyRepository->getKey($key_name);
    $key->setKeyValue($token);
    $key->save();
  }

//  public function setKeyName($id){
//    self::$keyName = $id;
//  }

  public function getToken($id){
    $key = $this->keyRepository->getKey($id);
    return $key->getKeyValue();
  }

  public function setTokenExpiration($time){
    $this->state->set('token_expiration', $time);
  }

//  public function createBearer($bearer){
//    $this->state->set('token_bearer', $bearer);
//  }

  public function getTokenExpiration(){
    return $this->state->get('token_expiration');
  }

//  public function getTokenBearer(){
//    return $this->state->get('token_bearer');
//  }

  public function getKeyList(){
    $keys = $this->keyRepository->getKeys();
    $list = [];
    foreach ($keys as $value){
      $list += [$value->id() => $value->label()];
    }
    return $list;
  }
}
