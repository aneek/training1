<?php

/**
 * @file
 * Provides basic module functionality.
 */

namespace Drupal\movie_db_trailers\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Pager\PagerManagerInterface;

use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class HelloWorldController.
 *
 * @package Drupal\movie_db_trailers\Controller
 */
class TrailersController extends ControllerBase implements ContainerInjectionInterface {
  /**
   * Pager manager.
   *
   * @var PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static($container->get('pager.manager'));
  }

  /**
   * TrailersController constructor.
   *
   * @param PagerManagerInterface $pager_manager
   */
  public function __construct(PagerManagerInterface $pager_manager)
  {
    $this->pagerManager = $pager_manager;
  }

  /**
   * Return a page.
   *
   * @return array
   *   Markup.
   */
  public function trailers(Node $node) {
    //dump($node);
    //dump($test);
    if ($node->bundle() == 'movie') {
      $media = $node->field_movie_trailer->referencedEntities();
      $fid = [];
      foreach ($media as &$value) {
//        $fid[] = [Markup::create($value->name[0]->getValue()['value']),
//          Markup::create('<a type="button" class="use-ajax"
//        data-dialog-options="{&quot;width&quot;:400}" data-dialog-type="modal"
//         href="/trailers/view/' . $this->getYoutubeId($value->field_media_oembed_video[0]->getValue()['value']) . '">Click to view</a>'),];
        $row = [
          'title' => $value->name[0]->getValue()['value'],
          'video_id' => $this->getYoutubeId($value->field_media_oembed_video[0]->getValue()['value']),
        ];
        $fid[] = $row;
      }


      $fid_splice = $this->returnPagerForArray($fid, 10);

//      $header = [
//        'title' => t('Name'),
//        'content' => t('Video'),
//      ];
//      $build['table'] = [
//        '#type' => 'table',
//        '#header' => $header,
//        '#rows' => $fid_splice,
//        '#empty' => t('No content has been found.'),
//      ];
//      $build['pager'] = [
//        '#type' => 'pager',
//      ];
//      $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return array(
        'table' => [
          '#theme' => 'trailers_page',
          '#items' => $fid_splice,
        ],
        'pager' => [
          '#type' => 'pager',
        ],
        "#attached" => [
          'library' => [
            'core/drupal.dialog.ajax'
          ]
        ]
      );
//      return [
//        '#type' => '#markup',
//        //'#theme' => 'movie_db_trailers_page',
//        //'#items' => $fid_splice,
//        '#markup' => \Drupal::service('renderer')->render($build),
//        '#attached' => array(
//          'library' => array('core/drupal.dialog.ajax',
//            'core/jquery.form'),
//        ),
//      ];
    }
    else{
      return ['#markup' => 'No result available'];
    }
  }

  function returnPagerForArray($items, $num_page) {
    // Get total items count
    $total = count($items);
    // Get the number of the current page
    $current_page = $this->pagerManager->createPager($total, $num_page, 0);
    // Split an array into chunks
    $chunks = array_chunk($items, $num_page);
    // Return current group item
    $current_page_items = $chunks[$current_page->getCurrentPage()];
    return $current_page_items;
  }

  public function getYoutubeId($video_url) {
    if (!$video_url) {
      return '';
    }

    $is_youtube_url = preg_split('/(vi\/|v=|\/v\/|youtu\.be\/|\/embed\/)/', preg_replace('/(>|<)/i', '', $video_url));

    $youtube_id = $is_youtube_url[1] ? preg_split('/[^0-9a-z_\-]/i', $is_youtube_url[1])[0] : $is_youtube_url;

    return $youtube_id;

  }

  public function getTitle(Node $node)
  {
    //return  'All trailers for '.$node->get('title')[0]->getValue()['value'];
    $type = $node->get('type')[0]->getValue()['target_id'];
    if ($type == 'movie') {
//      return ['#markup' => 'All trailers for ' . '<a href="/node/' . $node->get('nid')[0]->getValue()['value'] . '">'
//        . $node->get('title')[0]->getValue()['value'] . '</a>'];
      return 'All trailers for ' . $node->get('title')[0]->getValue()['value'];
    }
    else{
      return 'Page not found';
    }
  }
}
