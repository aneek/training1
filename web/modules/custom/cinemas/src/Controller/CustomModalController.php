<?php
/**
 * @file
 * CustomModalController class.
 */

namespace Drupal\cinemas\Controller;

use Drupal\cinemas\Service\CinemasService;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\leaflet\LeafletService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomModalController extends ControllerBase {
  /**
   * CinemasService.
   *
   * @var Drupal\cinemas\Service\CinemasService
   */
  protected $cinemasService;


  /**
   * A Leaflet instance.
   *
   * @var Drupal\leaflet\LeafletService
   */
  protected $leaflet;

  public function __construct(CinemasService $cinemasService, LeafletService $leaflet){
    $this->cinemasService = $cinemasService;
    $this->leaflet = $leaflet;
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   Container Interface.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cinemas.service'),
      $container->get('leaflet.service'),
    );
  }

  public function modal($cid) {
    //$data = $this->cinemasService->getCinema();
    $data = $this->cinemasService->responseByMovieId('189')[0];
    //dump($data);
    $features = [
      [
        'type' => 'point',
        'lat' => $data->location->latitude,
        'lon' => $data->location->longitude,
      ],
    ];
    $map = $this->leaflet->leafletMapGetInfo('OSM Mapnik');
    $map['settings']['zoom'] = 10;
    $result = $this->leaflet->leafletRenderMap($map, $features, $height = '500px');
    //dump($result);
    $img = [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => [
        'class' => 'my-cinemas-img',
        'src' => $data->image,
        'alt' => t('Cinema Image'),
        'width' => '150',
        'heigth' => '100',
      ],
    ];
    $address = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('Address : @street, @city, @country', [
        '@street' => $data->address->street,
        '@city' => $data->address->city,
        '@country' => $data->address->country,
      ]),
      '#attributes' => [
        'class' => 'my-cinemas-address',
      ],
    ];
    $cinema = [
      'address' => $address,
      'img' => $img,
      'map' => $result
    ];
    $content = [
      '#theme' => 'cinema_modal',
      '#cinema' => $cinema
    ];
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(t('Details'), $content, [
      'width' => '70%',
      'height' => '700',
    ]));

    return $response;
  }
}
