<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyResponsiveImage;

/**
 * Provides common field formatter-related methods: Blazy, Slick.
 */
class BlazyFormatter extends BlazyManager implements BlazyFormatterInterface {

  /**
   * Checks if image dimensions are set.
   *
   * @var array
   */
  private $isImageDimensionSet;

  /**
   * Returns available styles with crop in the effect name.
   *
   * @var array
   */
  protected $cropStyles;

  /**
   * Checks if the image style contains crop in the effect name.
   *
   * @var array
   */
  protected $isCrop;

  /**
   * {@inheritdoc}
   */
  public function buildSettings(array &$build, $items) {
    $settings = &$build['settings'];
    $entity   = $items->getEntity();

    $this->prepareData($build, $entity);
    $this->getCommonSettings($settings);
    $this->getEntitySettings($settings, $entity);

    $blazies        = &$settings['blazies'];
    $count          = $items->count();
    $field          = $items->getFieldDefinition();
    $field_name     = $field->getName();
    $field_clean    = str_replace("field_", '', $field_name);
    $entity_type_id = $settings['entity_type_id'];
    $entity_id      = $settings['entity_id'];
    $bundle         = $settings['bundle'];
    $view_mode      = $settings['current_view_mode'];
    $namespace      = $settings['namespace'];
    $id             = $settings['id'] ?? '';
    $gallery_id     = "{$namespace}-{$entity_type_id}-{$bundle}-{$field_clean}-{$view_mode}";
    $id             = Blazy::getHtmlId("{$gallery_id}-{$entity_id}", $id);

    // Provides formatter settings.
    $settings['cache_metadata']['keys'][] = $id;
    $settings['cache_metadata']['keys'][] = $count;

    // When alignment is mismatched, split them to satisfy linter.
    $settings['cache_tags'][] = $entity_type_id . ':' . $entity_id;
    $settings['caption']      = empty($settings['caption']) ? [] : array_filter($settings['caption']);
    $settings['count']        = $count;
    $settings['gallery_id']   = str_replace('_', '-', $gallery_id . '-' . $settings['media_switch']);
    $settings['id']           = $id;

    // Respects linked_field.module expectation.
    $use_field = !$blazies->get('lightbox') && ($settings['third_party']['linked_field']['linked'] ?? FALSE);

    $blazies->set('use.field', $use_field);
  }

  /**
   * {@inheritdoc}
   */
  public function preBuildElements(array &$build, $items, array $entities = []) {
    $this->buildSettings($build, $items);
    $settings = &$build['settings'];

    // Pass first item to optimize sizes this time.
    if (!empty($items[0])) {
      $this->uris($settings, $items, $entities);
    }

    // Sets dimensions once, if cropped, to reduce costs with ton of images.
    // This is less expensive than re-defining dimensions per image.
    if (!empty($settings['_uri'])) {
      if ($settings['blazies']->get('resimage.style')) {
        BlazyResponsiveImage::dimensionsAndSources($settings, TRUE);
      }
      else {
        $this->setImageDimensions($settings);
      }
    }

    // Allows altering the settings.
    $this->getModuleHandler()->alter('blazy_settings', $build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function postBuildElements(array &$build, $items, array $entities = []) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function isCrop($style) {
    if (!isset($this->isCrop[$style])) {
      $this->isCrop[$style] = $this->cropStyles()[$style] ?? FALSE;
    }
    return $this->isCrop[$style];
  }

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * @param array $settings
   *   The settings being modified.
   */
  protected function setImageDimensions(array &$settings = []) {
    if (!isset($this->isImageDimensionSet[md5($settings['id'])])) {
      // If image style contains crop, sets dimension once, and let all inherit.
      if (!empty($settings['image_style']) && ($style = $this->isCrop($settings['image_style']))) {
        $settings = array_merge($settings, BlazyFile::transformDimensions($style, $settings, TRUE));

        // Informs individual images that dimensions are already set once.
        $settings['_dimensions'] = TRUE;
      }

      $this->isImageDimensionSet[md5($settings['id'])] = TRUE;
    }
  }

  /**
   * Extract the first image item to build colorbox/zoom-like gallery.
   *
   * @todo move it into BlazyManagerBase if usable outside formatters.
   */
  protected function uris(array &$settings, $items, array $entities = []) {
    if ($output = BlazyFile::urisFromField($settings, $items, $entities)) {
      $settings['_uri'] = reset($output);
    }

    // The first image dimensions to differ from individual item dimensions.
    // @todo merge it into BlazyFile::urisFromField to swap them all once.
    if (!empty($settings['_item'])) {
      BlazyFile::imageDimensions($settings, $settings['_item'], TRUE);
    }
  }

  /**
   * Returns available image styles with crop in the name.
   */
  private function cropStyles() {
    if (!isset($this->cropStyles)) {
      $this->cropStyles = [];
      foreach ($this->entityLoadMultiple('image_style') as $style) {
        foreach ($style->getEffects() as $effect) {
          if (strpos($effect->getPluginId(), 'crop') !== FALSE) {
            $this->cropStyles[$style->getName()] = $style;
            break;
          }
        }
      }
    }
    return $this->cropStyles;
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.6 and is removed from blazy:3.0.0. Use
   *   self::urisFromField() instead to also extract URIs for preload option.
   * @see https://www.drupal.org/node/3103018
   */
  public function extractFirstItem(array &$settings, $item, $entity = NULL) {}

}
