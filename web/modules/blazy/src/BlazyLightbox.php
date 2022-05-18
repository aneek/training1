<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Serialization\Json;
use Drupal\blazy\Media\BlazyFile;

/**
 * Provides lightbox utilities.
 */
class BlazyLightbox {

  /**
   * Provides lightbox libraries.
   */
  public static function attach(array &$load, array $attach = []): void {
    $blazies = $attach['blazies'];
    $switch = $attach['media_switch'] ?? '';

    if ($switch && in_array($switch, $blazies->get('lightboxes', []))) {
      $load['library'][] = 'blazy/lightbox';

      if ($blazies->get('colorbox')) {
        self::attachColorbox($load, $attach);
      }
    }
  }

  /**
   * Attaches Colorbox if so configured.
   */
  public static function attachColorbox(array &$load, $attach = []): void {
    if ($service = Blazy::service('colorbox.attachment')) {
      $dummy = [];
      $service->attach($dummy);
      $load = isset($dummy['#attached']) ? NestedArray::mergeDeep($load, $dummy['#attached']) : $load;
      $load['library'][] = 'blazy/colorbox';
      unset($dummy);
    }
  }

  /**
   * Gets media switch elements: all lightboxes, not content, nor iframe.
   *
   * @param array $element
   *   The element being modified.
   */
  public static function build(array &$element = []): void {
    $item       = $element['#item'];
    $settings   = &$element['#settings'];
    $blazies    = &$settings['blazies'];
    $uri        = $settings['uri'];
    $switch     = $settings['media_switch'];
    $switch_css = str_replace('_', '-', $switch);
    $valid      = BlazyFile::isValidUri($uri);
    $box_style  = $blazies->get('box.style');

    // Provide relevant URL if it is a lightbox.
    $url_attributes = &$element['#url_attributes'];
    $url_attributes['class'][] = 'blazy__' . $switch_css . ' litebox';
    $url_attributes['data-' . $switch_css . '-trigger'] = TRUE;
    $element['#icon']['litebox']['#markup'] = '<span class="media__icon media__icon--litebox"></span>';

    // Gallery is determined by a view, or overriden by colorbox settings.
    $gallery_enabled = !empty($settings['view_name']);
    $gallery_default = $gallery_enabled ? $settings['view_name'] . '-' . $settings['current_view_mode'] : 'blazy-' . $switch_css;

    // Respects colorbox settings unless for an explicit view gallery.
    if (!$gallery_enabled && $switch === 'colorbox' && function_exists('colorbox_theme')) {
      $gallery_enabled = (bool) \Drupal::config('colorbox.settings')->get('custom.slideshow.slideshow');
    }

    // The gallery_id might be a formatter inside a view, not aware of its view.
    // The formatter might be duplicated on a page, although rare at production.
    $gallery_id             = empty($settings['gallery_id']) ? $gallery_default : $settings['gallery_id'] . '-' . $gallery_default;
    $settings['gallery_id'] = !$gallery_enabled ? NULL : str_replace('_', '-', $gallery_id);
    $settings['box_url']    = BlazyFile::transformRelative($uri);
    $settings['box_width']  = $item->width ?? $settings['width'] ?? NULL;
    $settings['box_height'] = $item->height ?? $settings['height'] ?? NULL;

    $dimensions = [
      'width' => $settings['box_width'],
      'height' => $settings['box_height'],
      'uri' => $uri,
    ];

    // Might not be present from BlazyFilter.
    $json = ['id' => $switch_css];
    foreach (['bundle', 'type'] as $key) {
      if (!empty($settings[$key])) {
        $json[$key] = $settings[$key];
      }
    }

    // Supports local and remote videos, also legacy VEF which has no bundles.
    // See https://drupal.org/node/3210636#comment-14097266.
    $videos = ['remote_video', 'video'];
    $is_video = ($json['type'] ?? FALSE) == 'video';
    $is_video = (isset($json['bundle']) && in_array($json['bundle'], $videos)) || $is_video;

    if (!empty($settings['box_style']) && $valid) {
      try {
        // The _responsive_image_build_source_attributes is WSOD if missing.
        if ($resimage = $blazies->get('box.resimage.style')) {
          if (!$is_video && empty($element['#lightbox_html'])) {
            $is_resimage = TRUE;
            $json['type'] = 'rich';
            $element['#lightbox_html'] = [
              '#theme' => 'responsive_image',
              '#responsive_image_style_id' => $resimage->id(),
              '#uri' => $uri,
            ];
          }
        }
      }
      catch (\Exception $e) {
        // Silently failed like regular images when missing rather than WSOD.
      }

      // Use non-responsive images if not-so-configured.
      if (!isset($is_resimage) && $box_style) {
        $dimensions = array_merge($dimensions, BlazyFile::transformDimensions($box_style, $dimensions));
        $settings['box_url'] = BlazyFile::transformRelative($uri, $box_style);
      }
    }

    // Allows custom work to override this without image style, such as
    // a combo of image, video, Instagram, Facebook, etc.
    if (empty($settings['_box_width'])) {
      $settings['box_width'] = $dimensions['width'];
      $settings['box_height'] = $dimensions['height'];
    }

    $json['width'] = $settings['box_width'];
    $json['height'] = $settings['box_height'];
    $json['boxType'] = 'image';

    // This allows PhotoSwipe with videos still swipable.
    if ($valid && $box_media_style = $blazies->get('box_media.style')) {
      $dimensions = array_merge($dimensions, BlazyFile::transformDimensions($box_media_style, $dimensions));
      $settings['box_media_url'] = BlazyFile::transformRelative($uri, $box_media_style);
    }

    $url = $settings['box_url'];
    if ($is_video) {
      $json['width']  = 640;
      $json['height'] = 360;

      if (!empty($settings['embed_url'])) {
        $url = $settings['embed_url'];

        // Force autoplay for media URL on lightboxes, saving another click.
        // BC for non-oembed such as Video Embed Field without Media migration.
        if (strpos($url, 'autoplay') === FALSE || strpos($url, 'autoplay=0') !== FALSE) {
          $url = strpos($url, '?') === FALSE ? $url . '?autoplay=1' : $url . '&autoplay=1';
        }
        $url_attributes['data-oembed-url'] = $url;
        $json['boxType'] = 'iframe';
      }

      // This allows PhotoSwipe with remote videos still swipable.
      if (!empty($settings['box_media_url'])) {
        $settings['box_url'] = $settings['box_media_url'];
      }

      if ($switch == 'photobox') {
        $url_attributes['rel'] = 'video';
      }

      // Remote or local videos.
      if (!empty($settings['box_media_url'])) {
        $json['width'] = $settings['box_width']  = $dimensions['width'];
        $json['height'] = $settings['box_height'] = $dimensions['height'];
      }

      if ($settings['box_url']) {
        $url_attributes['data-box-url'] = $settings['box_url'];
      }
    }

    if ($switch == 'colorbox' && !empty($settings['gallery_id'])) {
      // @todo make Blazy Grid without Blazy Views fields support multiple
      // fields and entities as a gallery group, likely via a class at Views UI.
      // Must use consistent key for multiple entities, hence cannot use id.
      // We do not have option for this like colorbox, as it is only limited
      // to the known Blazy formatters, or Blazy Views style plugins for now.
      // The hustle is Colorbox wants rel on individual item to group, unlike
      // other lightbox library which provides a way to just use a container.
      $json['rel'] = $settings['gallery_id'];
    }

    $has_dim = !empty($json['height']) && !empty($json['width']);
    if ($has_dim) {
      $json['height'] = (int) $json['height'];
      $json['width'] = (int) $json['width'];
    }

    // @todo make is flexible for regular non-media HTML.
    if (!empty($element['#lightbox_html'])) {
      $html = [
        '#theme' => 'container',
        '#children' => $element['#lightbox_html'],
        '#attributes' => [
          'class' => ['media', 'media--ratio'],
        ],
      ];

      if ($has_dim) {
        $pad = round((($json['height'] / $json['width']) * 100), 2);
        $html['#attributes']['style'] = 'width:' . $json['width'] . 'px; padding-bottom: ' . $pad . '%;';
      }

      // Responsive image is unwrapped. Local videos wrapped.
      $content = isset($is_resimage) ? $element['#lightbox_html'] : $html;
      $content = \blazy()->getRenderer()->renderPlain($content);
      $json['html'] = trim($content);
      if (isset($is_resimage)) {
        $json['boxType'] = strpos($content, '<picture') !== FALSE ? 'picture' : 'responsive-image';
      }
      else {
        if (strpos($content, '<video') !== FALSE) {
          $json['boxType'] = 'video';
        }
      }

      unset($element['#lightbox_html']);
    }

    $url_attributes['data-media'] = Json::encode($json);

    if (!empty($settings['box_caption'])) {
      $element['#captions']['lightbox'] = self::buildCaptions($item, $settings);
    }

    $element['#url'] = $url;
  }

  /**
   * Builds lightbox captions.
   *
   * @param object|mixed $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item.
   * @param array $settings
   *   The settings to work with.
   *
   * @return array
   *   The renderable array of caption, or empty array.
   */
  private static function buildCaptions($item, array $settings = []): array {
    $title   = $item->title ?? '';
    $alt     = $item->alt ?? '';
    $delta   = $settings['delta'] ?? 0;
    $caption = '';

    switch ($settings['box_caption']) {
      case 'auto':
        $caption = $alt ?: $title;
        break;

      case 'alt':
        $caption = $alt;
        break;

      case 'title':
        $caption = $title;
        break;

      case 'alt_title':
      case 'title_alt':
        $alt     = $alt ? '<p>' . $alt . '</p>' : '';
        $title   = $title ? '<h2>' . $title . '</h2>' : '';
        $caption = $settings['box_caption'] == 'alt_title' ? $alt . $title : $title . $alt;
        break;

      case 'entity_title':
        $caption = ($entity = $item->getEntity()) ? $entity->label() : '';
        break;

      case 'custom':
        $caption = '';
        if (!empty($settings['box_caption_custom']) && ($entity = $item->getEntity())) {
          $options = ['clear' => TRUE];
          $caption = \Drupal::token()->replace($settings['box_caption_custom'], [
            $entity->getEntityTypeId() => $entity,
            'file' => $item,
          ], $options);

          // Checks for multi-value text fields, and maps its delta to image.
          if (!empty($caption) && strpos($caption, ", <p>") !== FALSE) {
            $caption = str_replace(", <p>", '| <p>', $caption);
            $captions = explode("|", $caption);
            $caption = $captions[$delta] ?? '';
          }
        }
        break;

      default:
        $caption = $settings['box_caption'] == 'inline' ? '' : $settings['box_caption'];
    }

    return empty($caption)
      ? []
      : ['#markup' => Xss::filter($caption, BlazyDefault::TAGS)];
  }

}
