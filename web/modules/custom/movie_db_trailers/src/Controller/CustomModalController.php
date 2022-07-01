<?php
/**
 * @file
 * CustomModalController class.
 */

namespace Drupal\movie_db_trailers\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;

class CustomModalController extends ControllerBase {

  public function modal($tid) {
    $content = [
      '#type' => 'inline_template',
      '#template' => '<iframe width="100%" height="98%" src="https://www.youtube.com/embed/{{ video_id }}?autoplay=1" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
      '#context' => [
        'video_id' => $tid,
      ],
    ];
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(t('Watch'), $content, [
      'width' => '800',
      'height' => '600',
    ]));

    return $response;
  }
}
