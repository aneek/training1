<?php

namespace Drupal\cinemas\Plugin\Block;

use Drupal\cinemas\Service\CinemasService;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Cinemas' Block.
 *
 * @Block(
 *   id = "cinemas_block",
 *   admin_label = @Translation("Cinemas Block"),
 *   category = @Translation("Cinemas Module"),
 * )
 */
class CinemasBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * CinemasService.
   *
   * @var Drupal\cinemas\Service\CinemasService
   */
  protected $cinemasService;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, CinemasService $cinemasService)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cinemasService = $cinemasService;
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   Container Interface.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cinemas.service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $node = \Drupal::routeMatch()->getParameter('node');
    //$nid = 0;
    $node == null ? $nid = 4 : $nid = $node->nid[0]->getValue()['value'];
    $items = [];
    $cinemas = $this->cinemasService->responseByMovieId($nid);
    foreach ($cinemas as $cinema){
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
      $items[] = [
        'name' => $name,
        'description' => $description,
        'id' => $cinema->id,
      ];
    }
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
}
