<?php

/**
 * @file
 * Provides basic hello world message functionality.
 */

namespace Drupal\movie_db_trailers\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class HelloWorldController.
 *
 * @package Drupal\madison_example_one\Controller
 */
class TrailersController extends ControllerBase {

  /**
   * Say Hello.
   *
   * @return array
   *   Markup.
   */
  public function trailers() {
    return ['#markup' => $this->t("Hello World!")];
  }

}
