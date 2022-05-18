<?php

namespace Drupal\blazy\Media;

/**
 * Provides placeholder thumbnail image.
 */
class Placeholder {

  /**
   * Defines constant placeholder Data URI image.
   */
  const DATA = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Build out the blur image.
   */
  public static function blur(array &$element, array &$attributes, array &$settings) {
    $blazies = &$settings['blazies'];
    if (!$blazies->get('is.unstyled')) {
      $blur = [
        '#theme' => 'image',
        '#uri' => $settings['placeholder_ui'] ?: $blazies->get('ui.placeholder'),
        '#attributes' => [
          'class' => ['b-lazy', 'b-blur', 'b-blur--tmp'],
          'data-src' => $settings['placeholder_fx'],
          'loading' => 'lazy',
          'decoding' => 'async',
        ],
      ];

      // Reset as already stored.
      unset($settings['placeholder_fx']);
      $element['#preface']['blur'] = $blur;

      if (($settings['width'] ?? 0) > 980) {
        $attributes['class'][] = 'media--fx-lg';
      }
    }
  }

  /**
   * Generates an SVG Placeholder.
   *
   * @param string $width
   *   The image width.
   * @param string $height
   *   The image height.
   *
   * @return string
   *   Returns a string containing an SVG.
   */
  public static function generate($width, $height): string {
    $width = $width ?: 100;
    $height = $height ?: 100;
    return 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D\'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg\'%20viewBox%3D\'0%200%20' . $width . '%20' . $height . '\'%2F%3E';
  }

  /**
   * Build thumbnails, also to provide placeholder for blur effect.
   */
  public static function thumbnail(array &$attributes, array &$settings) {
    $blazies = &$settings['blazies'];
    $settings['placeholder_ui'] = $blazies->get('ui.placeholder');
    $path = $style = $thumbnail_url = '';

    // Supports unique thumbnail different from main image, such as logo for
    // thumbnail and main image for company profile.
    if (!empty($settings['thumbnail_uri'])) {
      $path = $settings['thumbnail_uri'];
      $thumbnail_url = BlazyFile::transformRelative($path);
    }
    else {
      if (!$blazies->get('is.external') && $style = $blazies->get('thumbnail.style')) {
        $path = $style->buildUri($settings['uri']);
        $thumbnail_url = BlazyFile::transformRelative($settings['uri'], $style);
      }
    }

    // With CSS background, IMG may be empty, add thumbnail to the container.
    // @todo remove thumbnail_url after sub-modules.
    if ($thumbnail_url) {
      $attributes['data-thumb'] = $settings['thumbnail_url'] = $thumbnail_url;
      $blazies->set('thumbnail.url', $thumbnail_url);

      if (BlazyFile::isValidUri($path) && !is_file($path)) {
        $style->createDerivative($settings['uri'], $path);
      }
    }

    // Provides image effect if so configured unless being sandboxed.
    if (!$blazies->get('is.sandboxed') && $fx = $blazies->get('fx')) {
      $attributes['class'][] = 'media--fx';

      // Ensures at least a hook_alter is always respected. This still allows
      // Blur and hook_alter for Views rewrite issues, unless global UI is set
      // which was already warned about anyway.
      if (!$blazies->get('is.unstyled')) {
        self::dataImage($settings, $style, $path);
      }

      // Being a separated .b-blur with .b-lazy, this should work for any lazy.
      $attributes['data-animation'] = $fx;
    }

    // Mimicks private _responsive_image_image_style_url, #3119527.
    BlazyResponsiveImage::fallback($settings);
  }

  /**
   * Build thumbnails, also to provide placeholder for blur effect.
   */
  private static function dataImage(array &$settings, $style = NULL, $path = ''): string {
    $blur = '';
    $uri = $settings['uri'];
    $blazies = &$settings['blazies'];

    // Provides default path, in case required by global, but not provided.
    $style = $style ?: \blazy()->entityLoad('thumbnail', 'image_style');
    if (empty($path) && $style && BlazyFile::isValidUri($uri)) {
      $path = $style->buildUri($uri);
    }

    if (BlazyFile::isValidUri($path)) {
      // Ensures the thumbnail exists before creating a dataURI.
      if (!is_file($path) && $style) {
        $style->createDerivative($uri, $path);
      }

      // Overrides placeholder with data URI based on configured thumbnail.
      if (is_file($path) && $content = file_get_contents($path)) {
        $blur = $settings['placeholder_fx'] = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($content);

        // Prevents double animations.
        // @todo remove use_loading after sub-module updates.
        $settings['use_loading'] = FALSE;
        $blazies->set('use.loader', FALSE);
      }
    }
    return $blur;
  }

}
