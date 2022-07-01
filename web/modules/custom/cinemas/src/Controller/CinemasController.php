<?php

namespace Drupal\cinemas\Controller;

use Drupal\cinemas\Service\CinemasService;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\leaflet\LeafletService;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CinemasController extends ControllerBase {

  /**
   * CinemasService.
   *
   * @var Drupal\cinemas\Service\CinemasService
   */
  protected $cinemasService;



  public function __construct(CinemasService $cinemasService){
    $this->cinemasService = $cinemasService;
    //$this->leaflet = $leaflet;
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
      //$container->get('leaflet.service'),
    );
  }

  public function cinemas($nid){
    //dump(\Drupal::entityTypeManager()->getStorage('node')->load($nid));
    //dump($this->cinemasService->getCinema()[0]);
    $items = [];
    $cinemas = $this->cinemasService->responseByMovieId($nid);
    //dump($cinemas);
    foreach ($cinemas as $cinema){
//      $features = [
//        [
//          'type' => 'point',
//          'lat' => $cinema->location->latitude,
//          'lon' => $cinema->location->longtitude,
//        ],
//      ];
//      $map = $this->leaflet->leafletMapGetInfo('OSM Mapnik');
//      $map['settings']['zoom'] = 8;
//      $result = $this->leaflet->leafletRenderMap($map, $features, $height = '400px');
      $name = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => t($cinema->title),
        '#attributes' => [
          'class' => 'my-cinemas-title',
          'font-weight' => 'bold',
        ],
      ];
      $description = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => t($cinema->description),
        '#attributes' => [
          'class' => 'my-cinemas-description',
        ],
      ];
//      $img = [
//        '#type' => 'html_tag',
//        '#tag' => 'img',
//        '#attributes' => [
//          'class' => 'my-cinemas-img',
//          'src' => $cinema->image,
//          'alt' => t('Cinema Image'),
//          'width' => '300',
//          'heigth' => '400',
//        ],
//      ];
//      $address = [
//        '#type' => 'html_tag',
//        '#tag' => 'p',
//        '#value' => t('Address : @street, @city, @country', [
//          '@street' => $cinema->address->street,
//          '@city' => $cinema->address->city,
//          '@country' => $cinema->address->country,
//        ]),
//        '#attributes' => [
//          'class' => 'my-cinemas-address',
//        ],
//      ];
      $cid = [
        '#value' => $cinema->id,
      ];

      $items[] = [
        'name' => $name,
        'description' => $description,
//        'img' => $img,
//        'address' => $address,
        'id' => $cinema->id,
//        'map' => $result
      ];
    }
    //dump($items);
    return [
      '#theme' => 'cinemas_page',
      '#items' => $items,
      '#attached' => [
        'library' => [
          'cinemas/cinemas',
          'core/drupal.dialog.ajax'
        ],
      ],
    ];
  }

  public function getTitle($nid)
  {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $type = $node->get('type')[0]->getValue()['target_id'];
    if ($type == 'movie') {
      return 'All cinemas currently showing ' . $node->get('title')[0]->getValue()['value'];
    }
    else{
      return 'Page not found';
    }
  }

//  public function modal($cid) {
//    //$data = $this->cinemasService->getCinema($cid);
//    $data = $this->cinemasService->responseByMovieId('189')[0];
//    //dump($data);
//    $features = [
//      [
//        'type' => 'point',
//        'lat' => $data->location->latitude,
//        'lon' => $data->location->longitude,
//      ],
//    ];
//    $map = $this->leaflet->leafletMapGetInfo('OSM Mapnik');
//    $map['settings']['zoom'] = 10;
//    $result = $this->leaflet->leafletRenderMap($map, $features, $height = '500px');
//    //dump($result);
//    $img = [
//      '#type' => 'html_tag',
//      '#tag' => 'img',
//      '#attributes' => [
//        'class' => 'my-cinemas-img',
//        'src' => $data->image,
//        'alt' => t('Cinema Image'),
//        'width' => '150',
//        'heigth' => '100',
//      ],
//    ];
//    $address = [
//      '#type' => 'html_tag',
//      '#tag' => 'p',
//      '#value' => t('Address : @street, @city, @country', [
//        '@street' => $data->address->street,
//        '@city' => $data->address->city,
//        '@country' => $data->address->country,
//      ]),
//      '#attributes' => [
//        'class' => 'my-cinemas-address',
//      ],
//    ];
//    $cinema = [
//      'address' => $address,
//      'img' => $img,
//      'map' => $result
//    ];
//    $content = [
//      '#theme' => 'cinema_modal',
//      '#cinema' => $cinema
//    ];
//    $response = new AjaxResponse();
//    $response->addCommand(new OpenModalDialogCommand(t('Details'), $content, [
//      'width' => '70%',
//      'height' => '700',
//    ]));
//
//    return $response;
//  }
}
