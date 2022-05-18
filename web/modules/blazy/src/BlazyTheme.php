<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\Placeholder;

/**
 * Provides theme-related alias methods to de-clutter Blazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyTheme {

  /**
   * Overrides variables for blazy.html.twig templates.
   *
   * Most heavy liftings are performed at BlazyManager::preRender().
   *
   * @param array $variables
   *   An associative array containing:
   *   - captions: An optional renderable array of inline or lightbox captions.
   *   - item: The image item containing alt, title, etc.
   *   - image: An optional renderable array of (Responsive) image element.
   *       Image is optional for CSS background, or iframe only displays.
   *   - settings: HTML related settings containing at least a required uri.
   *   - url: An optional URL the image can be linked to, can be any of
   *       audio/video, or entity URLs, when using Colorbox/Photobox, or Link to
   *       content options.
   *   - attributes: The container attributes (media, media--ratio etc.).
   *   - item_attributes: The image attributes (width, height, src, etc.).
   *   - url_attributes: An array of URL attributes, lightbox or content links.
   *   - noscript: The fallback image for non-js users.
   *   - postscript: Any extra content to put into blazy goes here. Use keyed or
   *       indexed array to not conflict with or nullify other providers, e.g.:
   *       postscript.cta, or postscript.widget. Avoid postscript = cta.
   *   - content: Various Media entities like Facebook, Instagram, local Video,
   *       etc. Basically content is the replacement for (Responsive) image
   *       and oEmbed video. This makes it possible to have a mix of Media
   *       entities, image and video on a Blazy Grid, Slick, GridStack, etc.
   *       without having different templates. Originally content is a
   *       theme_field() output, trimmed down to bare minimum so that embeddable
   *       inside theme_blazy() without duplicated or nested field markups.
   *       Regular Blazy features are still disabled by default at
   *       \Drupal\blazy\BlazyDefault::richSettings() to avoid complication.
   *       However you can override them accordingly as needed, such as lightbox
   *       for local Video with/o a pre-configured poster image. The #settings
   *       are provided under content variables for more work.
   */
  public static function blazy(array &$variables): void {
    $element = $variables['element'];
    foreach (BlazyDefault::themeProperties() as $key) {
      $variables[$key] = $element["#$key"] ?? [];
    }

    // Provides optional attributes, see BlazyFilter.
    foreach (BlazyDefault::themeAttributes() as $key) {
      $key = $key . '_attributes';
      $variables[$key] = empty($element["#$key"]) ? [] : new Attribute($element["#$key"]);
    }

    // Provides sensible default html settings to shutup notices when lacking.
    $attributes = &$variables['attributes'];
    $settings = &$variables['settings'];
    $settings += BlazyDefault::itemSettings();
    $blazies = &$settings['blazies'];

    // Do not proceed if no URI is provided. URI is not Blazy theme property.
    // Blazy is a wrapper for theme_[(responsive_)image], etc. who wants URI.
    if (empty($settings['uri'])) {
      return;
    }

    // URL and dimensions are built out at BlazyManager::preRenderBlazy().
    // Still provides a failsafe for direct call to theme_blazy().
    if (!$blazies->get('_api')) {
      // Prepares URI, extension, image styles, lightboxes.
      BlazyFile::prepare($settings);
      BlazyFile::urlAndDimensions($settings, $variables['item']);
      BlazyFile::lazyOrNot($settings);
    }

    // Allows rich Media entities stored within `content` to take over.
    // Rich media are things Blazy don't understand: Instagram, Facebook, etc.
    if (empty($variables['content'])) {
      Blazy::buildMedia($variables);
    }

    // Aspect ratio to fix layout reflow with lazyloaded images responsively.
    // This is outside 'lazy' to allow non-lazyloaded iframe/content use it too.
    // Prevents double padding hacks with AMP which also uses similar technique.
    $settings['ratio'] = empty($settings['width']) || $blazies->get('is.amp') ? '' : $settings['ratio'];
    if ($settings['ratio']) {
      Blazy::aspectRatioAttributes($attributes, $settings);
    }

    // Makes a little BEM order here due to Twig ignoring the preset priority.
    $classes = (array) ($attributes['class'] ?? []);
    $attributes['class'] = array_merge(['media', 'media--blazy'], $classes);
    $variables['blazies'] = $settings['blazies'];

    // Still provides a failsafe for direct call to theme_blazy().
    if (!$blazies->get('_api')) {
      Blazy::attach($variables, $settings);
    }
  }

  /**
   * Overrides variables for field.html.twig templates.
   */
  public static function field(array &$variables): void {
    $element = &$variables['element'];
    $settings = empty($element['#blazy']) ? [] : $element['#blazy'];

    // 1. Hence Blazy is not the formatter, lacks of settings.
    if (!empty($element['#third_party_settings']['blazy']['blazy'])) {
      self::thirdPartyField($variables);
    }

    // 2. Hence Blazy is the formatter, has its settings.
    if (empty($settings['_grid'])) {
      Blazy::containerAttributes($variables['attributes'], $settings);
    }
  }

  /**
   * Overrides variables for file-video.html.twig templates.
   */
  public static function fileVideo(array &$variables): void {
    if ($files = $variables['files']) {
      $use_dataset = empty($variables['attributes']['data-b-undata']);
      if ($use_dataset) {
        $variables['attributes']->addClass(['b-lazy']);
        foreach ($files as $file) {
          $source_attributes = &$file['source_attributes'];
          $source_attributes->setAttribute('data-src', $source_attributes['src']->value());
          $source_attributes->setAttribute('src', '');
        }
      }

      // Adds a poster image if so configured.
      if ($blazy = ($files[0]['blazy'] ?? FALSE)) {
        if ($blazy->get('image') && $blazy->get('uri')) {
          $settings = $blazy->storage();
          $settings['_dimensions'] = TRUE;
          $blazies = &$settings['blazies'];

          BlazyFile::imageUrl($settings);

          if (!$blazies->get('use.loader') && $use_dataset) {
            $blazies->set('use.loader', TRUE);
          }
          if (!empty($settings['image_url'])) {
            $variables['attributes']->setAttribute('poster', $settings['image_url']);
          }
          if ($blazies->get('lightbox') && !empty($settings['_richbox'])) {
            $variables['attributes']->setAttribute('autoplay', TRUE);
          }
        }
      }

      $attrs = ['data-b-lazy', 'data-b-undata'];
      $variables['attributes']->addClass(['media__element']);
      $variables['attributes']->removeAttribute($attrs);
    }
  }

  /**
   * Overrides variables for responsive-image.html.twig templates.
   */
  public static function responsiveImage(array &$variables): void {
    $image = &$variables['img_element'];
    $attributes = &$variables['attributes'];
    $placeholder = empty($attributes['data-placeholder']) ? Placeholder::DATA : $attributes['data-placeholder'];

    // Bail out if a noscript is requested.
    // @todo figure out to not even enter this method, yet not break ratio, etc.
    if (!isset($attributes['data-b-noscript'])) {
      // Modifies <picture> [data-srcset] attributes on <source> elements.
      if (!$variables['output_image_tag']) {
        /** @var \Drupal\Core\Template\Attribute $source */
        if ($sources = ($variables['sources'] ?? [])) {
          foreach ((array) $sources as &$source) {
            $source->setAttribute('data-srcset', $source['srcset']->value());
            $source->setAttribute('srcset', '');
          }
        }

        // Prevents invalid IMG tag when one pixel placeholder is disabled.
        $image['#uri'] = $placeholder;
        $image['#srcset'] = '';

        // Cleans up the no-longer relevant attributes for controlling element.
        unset($attributes['data-srcset'], $image['#attributes']['data-srcset']);
      }
      else {
        // Modifies <img> element attributes.
        $image['#attributes']['data-srcset'] = $attributes['srcset']->value();
        $image['#attributes']['srcset'] = '';
      }

      // The [data-b-lazy] is a flag indicating 1px placeholder.
      // This prevents double-downloading the fallback image, if enabled.
      if (!empty($attributes['data-b-lazy'])) {
        $image['#uri'] = $placeholder;
      }

      // More shared-with-image attributes are set at self::imageAttributes().
      $image['#attributes']['class'][] = 'b-responsive';
    }

    // Cleans up the no-longer needed flags:
    foreach (['placeholder', 'b-lazy', 'b-noscript'] as $key) {
      unset($attributes['data-' . $key], $image['#attributes']['data-' . $key]);
    }
  }

  /**
   * Overrides variables for media-oembed-iframe.html.twig templates.
   */
  public static function mediaOembedIframe(array &$variables): void {
    $request = Blazy::requestStack()->getCurrentRequest();
    // Without internet, this may be empty, bail out.
    if (empty($variables['media']) || !$request) {
      return;
    }

    // Only needed to autoplay video, and make responsive iframe.
    try {
      // Blazy formatters with oEmbed provide contextual params to the query.
      $is_blazy = $request->query->getInt('blazy', NULL);
      $is_autoplay = $request->query->getInt('autoplay', NULL);
      $url = $request->query->get('url');

      // Only replace url if it is required by Blazy.
      if ($url && $is_blazy == 1) {
        // Load iframe string as a DOMDocument as alternative to regex.
        $dom = Html::load($variables['media']);
        $iframe = $dom->getElementsByTagName('iframe');

        // Replace old oEmbed url with autoplay support, and save the DOM.
        if ($iframe->length > 0) {
          // Fetches autoplay_url.
          $embed_url = $iframe->item(0)->getAttribute('src');
          $settings = self::getAutoPlayUrl($embed_url);

          // Only replace if autoplay == 1 for Image to iframe, or lightboxes.
          if ($is_autoplay == 1 && !empty($settings['autoplay_url'])) {
            $iframe->item(0)->setAttribute('src', $settings['autoplay_url']);
          }

          // Make responsive iframe with/ without autoplay.
          // The following ensures iframe does not shrink due to its attributes.
          $iframe->item(0)->setAttribute('height', '100%');
          $iframe->item(0)->setAttribute('width', '100%');
          $dom->getElementsByTagName('body')->item(0)->setAttribute('class', 'is-b-oembed');
          $variables['media'] = $dom->saveHTML();
        }
      }
    }
    catch (\Exception $ignore) {
      // Do nothing, likely local work without internet, or the site is down.
      // No need to be chatty on this.
    }
  }

  /**
   * Provides the autoplay url suitable for lightboxes, or custom video trigger.
   *
   * As per 21/12/31, coder doesn't recognize nullable typehints, and err.
   * https://www.php.net/manual/en/migration71.new-features.php.
   *
   * @param string $url
   *   The embed URL, not input URL.
   *
   * @return array
   *   The settings array containing autoplay and oembed URL.
   */
  public static function getAutoPlayUrl(?string $url): array {
    $data = [];
    if (!empty($url)) {
      $data['oembed_url'] = $url;
      // Adds autoplay for media URL on lightboxes, saving another click.
      if (strpos($url, 'autoplay') === FALSE || strpos($url, 'autoplay=0') !== FALSE) {
        $data['autoplay_url'] = strpos($url, '?') === FALSE ? $url . '?autoplay=1' : $url . '&autoplay=1';
      }
    }
    return $data;
  }

  /**
   * Overrides variables for field.html.twig templates.
   */
  private static function thirdPartyField(array &$variables): void {
    $element = $variables['element'];
    $settings = $element['#blazy'] ?? [];

    if (!isset($settings['blazies'])) {
      $settings += BlazyDefault::htmlSettings();
    }

    $settings['third_party'] = $element['#third_party_settings'];
    $blazies = &$settings['blazies'];
    // @todo re-check at CKEditor.
    $is_undata = $blazies->get('is.undata');

    foreach ($variables['items'] as &$item) {
      if (empty($item['content'])) {
        continue;
      }

      $item_attributes = &$item['content'][isset($item['content']['#attributes']) ? '#attributes' : '#item_attributes'];
      $item_attributes['data-b-lazy'] = TRUE;

      if ($is_undata) {
        $item_attributes['data-b-undata'] = TRUE;
      }
    }

    // Attaches Blazy libraries here since Blazy is not the formatter.
    Blazy::attach($variables, $settings);
  }

}
