<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\blazy\Media\BlazyFile;

/**
 * Provides internal Blazy utilities, hardly re-usable outside blazy.module.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyUtil {

  /**
   * Returns the sanitized attributes for user-defined (UGC Blazy Filter).
   *
   * When IMG and IFRAME are allowed for untrusted users, trojan horses are
   * welcome. Hence sanitize attributes relevant for BlazyFilter. The rest
   * should be taken care of by HTML filters after Blazy.
   *
   * @param array $attributes
   *   The given attributes to sanitize.
   * @param bool $escaped
   *   Sets to FALSE to avoid double escapes, for further processing.
   *
   * @return array
   *   The sanitized $attributes suitable for UGC, such as Blazy filter.
   */
  public static function sanitize(array $attributes = [], $escaped = TRUE): array {
    $clean_attributes = [];
    $tags = ['href', 'poster', 'src', 'about', 'data', 'action', 'formaction'];
    foreach ($attributes as $key => $value) {
      if (is_array($value)) {
        // Respects array item containing space delimited classes: aaa bbb ccc.
        $value = implode(' ', $value);
        $clean_attributes[$key] = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', explode(' ', $value));
      }
      else {
        // Since Blazy is lazyloading known URLs, sanitize attributes which
        // make no sense to stick around within IMG or IFRAME tags.
        $kid = mb_substr($key, 0, 2) === 'on' || in_array($key, $tags);
        $key = $kid ? 'data-' . $key : $key;
        $escaped_value = $escaped ? Html::escape($value) : $value;
        $clean_attributes[$key] = $kid ? Html::cleanCssIdentifier($value) : $escaped_value;
      }
    }
    return $clean_attributes;
  }

  /**
   * Collects the first found Blazy formatter settings within Views fields.
   */
  public static function isBlazyFormatter(array &$settings, array $item = []) {
    $blazy = $item['settings'];
    if (!isset($settings['blazies'])) {
      $settings += BlazyDefault::htmlSettings();
    }

    // Merge the first found (Responsive) image data.
    $formatter_blazies = $blazy['blazies'] ?? NULL;
    if ($formatter_blazies && $formatter_blazies instanceof BlazySettings) {
      $blazies = &$settings['blazies'];

      $settings['blazies'] = $blazies->merge($formatter_blazies->storage());
      $settings['_dimensions'] = !empty($blazies->get('ratios'));
    }

    $cherries = BlazyDefault::cherrySettings() + ['uri' => ''];
    foreach ($cherries as $key => $value) {
      $fallback = $settings[$key] ?? $value;
      $settings[$key] = isset($blazy[$key]) && empty($fallback) ? $blazy[$key] : $fallback;
    }

    $settings['_uri'] = empty($settings['_uri']) ? $settings['uri'] : $settings['_uri'];
    unset($settings['uri']);
  }

  /**
   * Provides original unstyled image dimensions based on the given image item.
   *
   * @todo deprecate and removed at 3.+. Use BlazyFile::imageDimensions()
   * instead.
   */
  public static function imageDimensions(array &$settings, $item = NULL, $initial = FALSE) {
    BlazyFile::imageDimensions($settings, $item, $initial);
  }

  /**
   * A wrapper for ImageStyle::transformDimensions().
   *
   * @todo deprecate and removed at 3.+. Use BlazyFile::transformDimensions()
   * instead.
   */
  public static function transformDimensions($style, array $data, $initial = FALSE) {
    return BlazyFile::transformDimensions($style, $data, $initial);
  }

  /**
   * A wrapper for ::transformRelative() to pass tests anywhere else.
   *
   * @todo deprecate at 2.5 and removed at 3.+. Use
   * BlazyFile::transformRelative() instead.
   */
  public static function transformRelative($uri, $style = NULL) {
    return BlazyFile::transformRelative($uri, $style);
  }

  /**
   * Returns the URI from the given image URL, relevant for unmanaged files.
   *
   * @todo deprecate at 2.5 and removed at 3.+. Use BlazyFile::buildUri()
   * instead.
   */
  public static function buildUri($image_url) {
    return BlazyFile::buildUri($image_url);
  }

  /**
   * Determines whether the URI has a valid scheme for file API operations.
   *
   * @todo deprecate at 2.5 and removed at 3.+. Use BlazyFile::isValidUri()
   * instead.
   */
  public static function isValidUri($uri) {
    return BlazyFile::isValidUri($uri);
  }

  /**
   * Provides image url based on the given settings.
   *
   * @todo deprecate at 2.5 and removed at 3.+. Use BlazyFile::imageUrl()
   * instead.
   */
  public static function imageUrl(array &$settings) {
    BlazyFile::imageUrl($settings);
  }

  /**
   * Generates an SVG Placeholder.
   *
   * @todo deprecate at 2.7 and removed at 3.+. Use Placeholder::DATA anytime.
   */
  public static function generatePlaceholder($width, $height): string {
    $width = $width ?: 100;
    $height = $height ?: 100;
    return 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D\'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg\'%20viewBox%3D\'0%200%20' . $width . '%20' . $height . '\'%2F%3E';
  }

}
