<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Cache\Cache;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\Placeholder;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 */
class BlazyManager extends BlazyManagerBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderBlazy', 'preRenderBuild'];
  }

  /**
   * Returns the enforced rich media content, or media using theme_blazy().
   *
   * @param array $build
   *   The array containing: item, content, settings, or optional captions.
   *
   * @return array
   *   The alterable and renderable array of enforced content, or theme_blazy().
   */
  public function getBlazy(array $build = []) {
    foreach (BlazyDefault::themeProperties() as $key) {
      $build[$key] = $build[$key] ?? [];
    }

    $settings = &$build['settings'];
    $settings += BlazyDefault::itemSettings();
    $settings['uri'] = $uri = $settings['uri'] ?: BlazyFile::uri($build['item']);

    // Respects content not handled by theme_blazy(), but passed through.
    // Yet allows rich contents which might still be processed by theme_blazy().
    $content = empty($uri) ? $build['content'] : [
      '#theme'       => 'blazy',
      '#delta'       => $settings['delta'],
      '#item'        => $build['item'],
      '#image_style' => $settings['image_style'],
      '#build'       => $build,
      '#pre_render'  => [[$this, 'preRenderBlazy']],
    ];

    $this->moduleHandler->alter('blazy', $content, $settings);
    return $content;
  }

  /**
   * Builds the Blazy image as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The pre-rendered element.
   *
   * @return array
   *   The renderable array of pre-rendered element.
   */
  public function preRenderBlazy(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    // Prepare the main image.
    $this->prepareBlazy($element, $build);

    // Fetch the newly modified settings.
    $settings = $element['#settings'];
    $blazies = &$settings['blazies'];

    if ($settings['media_switch']) {
      if ($settings['media_switch'] == 'content' && !empty($settings['content_url'])) {
        $element['#url'] = $settings['content_url'];
      }
      elseif ($blazies->get('lightbox')) {
        BlazyLightbox::build($element);
      }
    }

    return $element;
  }

  /**
   * Prepares the Blazy output as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The renderable array being modified.
   * @param array $build
   *   The array of information containing the required Image or File item
   *   object, settings, optional container attributes.
   */
  protected function prepareBlazy(array &$element, array $build) {
    $item = $build['item'];
    $settings = &$build['settings'];
    $blazies = &$settings['blazies'];

    $blazies->set('_api', TRUE);

    // Prepares URI, extension, image styles, lightboxes.
    BlazyFile::prepare($settings);

    foreach (BlazyDefault::themeAttributes() as $key) {
      $key = $key . '_attributes';
      $build[$key] = $build[$key] ?? [];
    }

    // Blazy has these 3 attributes, yet provides optional ones far below.
    // Sanitize potential user-defined attributes such as from BlazyFilter.
    // Skip attributes via $item, or by module, as they are not user-defined.
    $attributes = &$build['attributes'];

    // Build thumbnail and optional placeholder based on thumbnail.
    // This must be set before Blazy::urlAndDimensions to provide placeholder.
    Placeholder::thumbnail($attributes, $settings);

    // Prepare image URL and its dimensions, including for rich-media content,
    // such as for local video poster image if a poster URI is provided.
    BlazyFile::urlAndDimensions($settings, $item);

    // Only process (Responsive) image/ video if no rich-media are provided.
    $this->buildContent($element, $build);
    if (empty($build['content'])) {
      $this->buildMedia($element, $build);
    }

    // Must listen to BG `unlazy`.
    BlazyFile::lazyOrNot($settings);

    // Multi-breakpoint aspect ratio only applies if lazyloaded.
    // These may be set once at formatter level, or per breakpoint above.
    if (!$blazies->get('is.undata') && $ratios = $blazies->get('ratios', [])) {
      $attributes['data-ratios'] = Json::encode($ratios);
    }

    // Provides extra attributes as needed, excluding url, item, done above.
    // Was planned to replace sub-module item markups if similarity is found for
    // theme_gridstack_box(), theme_slick_slide(), etc. Likely for Blazy 3.x+.
    foreach (['caption', 'media', 'wrapper'] as $key) {
      $element["#$key" . '_attributes'] = empty($build[$key . '_attributes']) ? [] : BlazyUtil::sanitize($build[$key . '_attributes']);
    }

    // Provides captions, if so configured.
    if ($build['captions'] && ($captions = $this->buildCaption($build['captions'], $settings))) {
      $element['#captions'] = $captions;
      $element['#caption_attributes']['class'][] = $settings['item_id'] . '__caption';
    }

    // Pass common elements to theme_blazy().
    $element['#attributes']     = $attributes;
    $element['#settings']       = $settings;
    $element['#url_attributes'] = $build['url_attributes'];

    // Preparing Blazy to replace other blazy-related content/ item markups.
    // Composing or layering is crucial for mixed media (icon over CTA or text
    // or lightbox links or iframe over image or CSS background over noscript
    // which cannot be simply dumped as array without elaborate arrangements).
    foreach (['content', 'icon', 'overlay', 'preface', 'postscript'] as $key) {
      $element["#$key"] = empty($element["#$key"]) ? $build[$key] : NestedArray::mergeDeep($element["#$key"], $build[$key]);
    }
  }

  /**
   * Build captions for both old image, or media entity.
   */
  public function buildCaption(array $captions, array $settings) {
    $content = [];
    foreach ($captions as $key => $caption_content) {
      if ($caption_content) {
        $content[$key]['content'] = $caption_content;
        $content[$key]['tag'] = strpos($key, 'title') !== FALSE ? 'h2' : 'div';
        $class = $key == 'alt' ? 'description' : str_replace('field_', '', $key);
        $content[$key]['attributes'] = new Attribute();
        $content[$key]['attributes']->addClass($settings['item_id'] . '__caption--' . str_replace('_', '-', $class));
      }
    }

    return $content ? ['inline' => $content] : [];
  }

  /**
   * Returns the contents using theme_field(), or theme_item_list().
   *
   * Blazy outputs can be formatted using either flat list via theme_field(), or
   * a grid of Field items or Views rows via theme_item_list().
   *
   * @param array $build
   *   The array containing: settings, children elements, or optional items.
   *
   * @return array
   *   The alterable and renderable array of contents.
   */
  public function build(array $build = []) {
    $settings = &$build['settings'];
    $settings += BlazyDefault::htmlSettings();
    $blazies = &$settings['blazies'];
    $settings['_grid'] = $settings['_grid'] ?? ($settings['style'] && $settings['grid']);

    // If not a grid, pass the items as regular index children to theme_field().
    // This #pre_render doesn't work if called from Views results, hence the
    // output is split either as theme_field() or theme_item_list().
    if (empty($settings['_grid'])) {
      $settings = $this->getSettings($build);

      // Runs after ::getSettings.
      $this->prepareBuild($build);
      $build['#blazy'] = $settings;
      $this->setAttachments($build, $settings);
    }
    else {
      // Take over theme_field() with a theme_item_list(), if so configured.
      // The reason: this is not only fed by field items, but also Views rows.
      $content = [
        '#build'      => $build,
        '#pre_render' => [[$this, 'preRenderBuild']],
      ];

      // Yet allows theme_field(), if so required, such as for linked_field.
      $build = $blazies->get('use.field') ? [$content] : $content;
    }

    $this->moduleHandler->alter('blazy_build', $build, $settings);
    return $build;
  }

  /**
   * Builds the Blazy outputs as a structured array ready for ::renderer().
   */
  public function preRenderBuild(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    // Checks if we got some signaled attributes.
    $attributes = $element['#theme_wrappers']['container']['#attributes'] ?? $element['#attributes'] ?? [];
    $settings   = $this->getSettings($build);

    // Runs after ::getSettings.
    $this->prepareBuild($build);

    // Take over elements for a grid display as this is all we need, learned
    // from the issues such as: #2945524, or product variations.
    // We'll selectively pass or work out $attributes not so far below.
    $element = BlazyGrid::build($build, $settings);
    $this->setAttachments($element, $settings);

    if ($attributes) {
      // Signals other modules if they want to use it.
      // Cannot merge it into BlazyGrid (wrapper_)attributes, done as grid.
      // Use case: Product variations, best served by ElevateZoom Plus.
      if (isset($element['#ajax_replace_class'])) {
        $element['#container_attributes'] = $attributes;
      }
      else {
        // Use case: VIS, can be blended with UL element safely down here.
        // The $attributes is merged with BlazyGrid::build ones here.
        $element['#attributes'] = NestedArray::mergeDeep($element['#attributes'], $attributes);
      }
    }

    return $element;
  }

  /**
   * Prepares Blazy settings.
   */
  protected function getSettings(array &$build) {
    $settings = $build['settings'] ?? [];

    // Supports Blazy multi-breakpoint images if provided, updates $settings.
    // Cases: Blazy within Views gallery, or references without direct image.
    if ($settings['first_image'] && $settings['check_blazy']) {
      // Views may flatten out the array, bail out.
      // What we do here is extract the formatter settings from the first found
      // image and pass its settings to this container so that Blazy Grid which
      // lacks of settings may know if it should load/ display a lightbox, etc.
      // Lightbox should work without `Use field template` checked.
      if (is_array($settings['first_image'])) {
        $this->isBlazy($settings, $settings['first_image']);
      }
    }

    return $settings;
  }

  /**
   * Prepares Blazy outputs, extract items as indices.
   */
  protected function prepareBuild(array &$build) {
    // If children are grouped within items property, reset to indexed keys.
    // Blazy comes late to the party after sub-modules decided what they want
    // where items may be stored as direct indices, or put into items property.
    // Actually the same issue happens at core where contents may be indexed or
    // grouped. Meaning not a problem at all, only a problem for consistency.
    $build = $build['items'] ?? $build;
    unset($build['items'], $build['settings']);
  }

  /**
   * Build out (rich media) content.
   */
  protected function buildContent(array &$element, array &$build) {
    $settings = &$build['settings'];
    if (empty($build['content'])) {
      return;
    }

    // Prevents complication for now, such as lightbox for Facebook, etc.
    // Either makes no sense, or not currently supported without extra legs.
    // Original formatter settings can still be accessed via content variable.
    $settings = array_merge($settings, BlazyDefault::richSettings());

    // Supports HTML content for lightboxes as long as having image trigger.
    // Type rich to not conflict with Image rendered by its formatter option.
    $rich = $settings['type'] == 'rich' && !empty($settings['_richbox']);
    if ($rich && $blazy = ($build['content'][0]['#settings'] ?? NULL)) {
      if (!empty($settings['_hires']) && $blazy->get('lightbox')) {
        // Overrides the overriden settings with original formatter settings.
        $settings = array_merge($settings, $blazy->storage());

        $element['#lightbox_html'] = $build['content'];
        $build['content'] = [];
      }
    }
  }

  /**
   * Build out (Responsive) image.
   */
  private function buildMedia(array &$element, array &$build) {
    $item = $build['item'];
    $settings = &$build['settings'];
    $attributes = &$build['attributes'];
    $blazies = &$settings['blazies'];

    // (Responsive) image with item attributes, might be RDF.
    $item_attributes = empty($build['item_attributes']) ? [] : BlazyUtil::sanitize($build['item_attributes']);

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    if ($item && isset($item->_attributes)) {
      $item_attributes += $item->_attributes;
      unset($item->_attributes);
    }

    // Responsive image integration, with/o CSS background so to work with.
    // Prevents _responsive_image_build_source_attributes from WSOD if missing.
    // Avoided is_file() check due to ramifications, see #3225859.
    if ($blazies->get('resimage.style') && !$blazies->get('is.unstyled')) {
      try {
        $this->buildResponsiveImage($element, $attributes, $settings);
      }
      catch (\Exception $e) {
        // Silently failed like regular images when missing rather than WSOD.
      }
    }

    // Regular image, with/o CSS background so to work with.
    if (!$blazies->get('resimage.id')) {
      $this->buildImage($element, $attributes, $item_attributes, $settings);
    }

    // Pass non-rich-media elements to theme_blazy().
    $element['#item_attributes'] = $item_attributes;

    if ($blazies->get('libs.blur')) {
      Placeholder::blur($element, $attributes, $settings);
    }
  }

  /**
   * Build out Responsive image.
   */
  private function buildResponsiveImage(array &$element, array &$attributes, array &$settings) {
    $blazies = &$settings['blazies'];
    $responsive_image = $blazies->get('resimage');
    $element['#cache']['tags'] = $responsive_image['caches'];
  }

  /**
   * Build out image, or anything related, including cache, CSS background, etc.
   */
  private function buildImage(array &$element, array &$attributes, array &$item_attributes, array &$settings) {
    if (empty($settings['_no_cache'])) {
      $file_tags = $settings['file_tags'] ?? [];
      $settings['cache_tags'] = empty($settings['cache_tags']) ? $file_tags : Cache::mergeTags($settings['cache_tags'], $file_tags);

      $element['#cache']['max-age'] = -1;
      foreach (['contexts', 'keys', 'tags'] as $key) {
        if (!empty($settings['cache_' . $key])) {
          $element['#cache'][$key] = $settings['cache_' . $key];
        }
      }
    }
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.0 and is removed from blazy:3.0.0. Use
   *   self::getBlazy() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getImage(array $build = []) {
    @trigger_error('getImage is deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\BlazyManager::getBlazy() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
    return $this->getBlazy($build);
  }

}
