<?php

namespace Drupal\blazy;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyResponsiveImage;

/**
 * Provides common blazy utility static methods.
 */
class Blazy implements BlazyInterface {

  // @todo remove at blazy:8.x-3.0 or sooner.
  use BlazyDeprecatedTrait;

  /**
   * The blazy HTML ID.
   *
   * @var int
   */
  private static $blazyId;

  /**
   * The AMP page.
   *
   * @var bool
   */
  private static $isAmp;

  /**
   * The preview mode to disable Blazy where JS is not available, or useless.
   *
   * @var bool
   */
  private static $isPreview;

  /**
   * The preview mode to disable interactive elements.
   *
   * @var bool
   */
  private static $isSandboxed;

  /**
   * Provides attachments when not using the provided API.
   */
  public static function attach(array &$variables, array $settings = []): void {
    if ($blazy = self::service('blazy.manager')) {
      $attachments = $blazy->attach($settings);
      $variables['#attached'] = empty($variables['#attached']) ? $attachments : NestedArray::mergeDeep($variables['#attached'], $attachments);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function buildMedia(array &$variables): void {
    $attributes = &$variables['attributes'];
    $settings = &$variables['settings'];
    $blazies = &$settings['blazies'];
    $resimage = $blazies->get('resimage.id');

    // (Responsive) image is optional for Video, or image as CSS background.
    if ($resimage) {
      self::buildResponsiveImage($variables);
    }
    else {
      self::buildImage($variables);
    }

    // The settings.bgs is output specific for CSS background purposes with BC.
    if ($bgs = $blazies->get('bgs')) {
      // @todo remove .media--background for .b-bg as more relevant for BG.
      $attributes['class'][] = 'b-bg media--background';
      $attributes['data-b-bg'] = Json::encode($bgs);

      if ($blazies->get('is.static') && $url = $settings['image_url']) {
        self::inlineStyle($attributes, 'background-image: url(' . $url . ');');
      }
    }

    // Prepare a media player, and allow a tiny video preview without iframe.
    if ($blazies->get('use.media') && empty($settings['_noiframe'])) {
      self::buildIframe($variables);
    }

    // (Responsive) image is optional for Video, or image as CSS background.
    if ($variables['image']) {
      self::imageAttributes($variables);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function buildResponsiveImage(array &$variables): void {
    $settings = &$variables['settings'];
    $blazies = &$settings['blazies'];

    if (empty($settings['background'])) {
      $natives = ['decoding' => 'async'];

      $attributes = ($settings['unlazy'] ? $natives : [
        'data-b-lazy' => $blazies->get('ui.one_pixel'),
        'data-placeholder' => $blazies->get('ui.placeholder'),
      ]);

      $variables['image'] += [
        '#type' => 'responsive_image',
        '#responsive_image_style_id' => $blazies->get('resimage.id'),
        '#uri' => $settings['uri'],
        '#attributes' => $attributes,
      ];
    }
    else {
      // Attach BG data attributes to a DIV container.
      $attributes = &$variables['attributes'];
      BlazyResponsiveImage::toBackground($attributes, $settings);
    }
  }

  /**
   * Modifies variables for blazy (non-)lazyloaded image.
   */
  public static function buildImage(array &$variables): void {
    $attributes = &$variables['attributes'];
    $settings = &$variables['settings'];
    $blazies = &$settings['blazies'];

    // Supports either lazy loaded image, or not.
    if (empty($settings['background'])) {
      $variables['image'] += [
        '#theme' => 'image',
        '#uri' => $settings['unlazy'] ? $settings['image_url'] : $blazies->get('ui.placeholder'),
      ];
    }
    else {
      // Attach BG data attributes to a DIV container.
      $blazies->set('bgs.' . $settings['width'], BlazyFile::backgroundImage($settings));
      $unlazy = $settings['unlazy'] = $blazies->get('is.undata');
      $settings['image_url'] = $unlazy ? $settings['image_url'] : $blazies->get('ui.placeholder');
      self::lazyAttributes($attributes, $settings);
    }
  }

  /**
   * Modifies $variables to provide optional (Responsive) image attributes.
   */
  public static function imageAttributes(array &$variables): void {
    $item = $variables['item'];
    $settings = &$variables['settings'];
    $image = &$variables['image'];
    $attributes = &$variables['item_attributes'];
    $blazies = &$settings['blazies'];

    // Respects hand-coded image attributes.
    if ($item) {
      if (!isset($attributes['alt'])) {
        $attributes['alt'] = empty($item->alt) ? NULL : trim($item->alt);
      }

      // Do not output an empty 'title' attribute.
      if (isset($item->title) && (mb_strlen($item->title) != 0)) {
        $attributes['title'] = trim($item->title);
      }
    }

    // Only output dimensions for non-svg. Respects hand-coded image attributes.
    // Do not pass it to $attributes to also respect both (Responsive) image.
    if (!isset($attributes['width']) && !$blazies->get('is.unstyled')) {
      $image['#height'] = $settings['height'];
      $image['#width'] = $settings['width'];
    }

    // Overrides title if to be used as a placeholder for lazyloaded video.
    if (!empty($settings['embed_url']) && !empty($settings['accessible_title'])) {
      $translation_replacements = ['@label' => $settings['accessible_title']];
      $attributes['title'] = t('Preview image for the video "@label".', $translation_replacements);

      if (!empty($attributes['alt'])) {
        $translation_replacements['@alt'] = $attributes['alt'];
        $attributes['alt'] = t('Preview image for the video "@label" - @alt.', $translation_replacements);
      }
      else {
        $attributes['alt'] = $attributes['title'];
      }
    }

    $attributes['class'][] = 'media__image';
    // https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/decode.
    $attributes['decoding'] = 'async';

    // Preserves UUID for sub-module lookups, relevant for BlazyFilter.
    if (!empty($settings['entity_uuid'])) {
      $attributes['data-entity-uuid'] = $settings['entity_uuid'];
    }

    self::commonAttributes($attributes, $variables['settings']);
    $image['#attributes'] = empty($image['#attributes']) ? $attributes : NestedArray::mergeDeep($image['#attributes'], $attributes);

    // Provides a noscript if so configured, before any lazy defined.
    // Not needed at preview mode, or when native lazyload takes over.
    if ($blazies->get('ui.noscript') && empty($settings['unlazy'])) {
      self::buildNoscriptImage($variables);
    }

    // Provides [data-(src|lazy)] for (Responsive) image, after noscript.
    self::lazyAttributes($image['#attributes'], $settings);

    self::unloading($image['#attributes'], $settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function iframeAttributes(array &$settings): array {
    $blazies = &$settings['blazies'];
    $attributes['class'] = ['b-lazy', 'media__iframe'];
    $attributes['allowfullscreen'] = TRUE;

    // Inside CKEditor must disable interactive elements.
    if ($blazies->get('is.sandboxed')) {
      $attributes['sandbox'] = TRUE;
      $attributes['src'] = $settings['embed_url'];
    }
    // Native lazyload just loads the URL directly.
    // With many videos like carousels on the page may chaos, but we provide a
    // solution: use `Image to Iframe` for GDPR, swipe and best performance.
    elseif ($settings['unlazy']) {
      $attributes['src'] = $settings['embed_url'];
    }
    // Non-native lazyload for oldies to avoid loading src, the most efficient.
    else {
      $attributes['data-src'] = $settings['embed_url'];
      $attributes['src'] = 'about:blank';
    }

    self::commonAttributes($attributes, $settings);
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildIframe(array &$variables): void {
    $settings = &$variables['settings'];
    $blazies = &$settings['blazies'];
    $settings['player'] = !$blazies->get('lightbox') && $settings['media_switch'] == 'media';

    // Only provide iframe if not for lightboxes, identified by URL.
    if (empty($variables['url'])) {
      $variables['image'] = empty($settings['media_switch']) ? [] : $variables['image'];

      // Pass iframe attributes to template.
      $variables['iframe'] = [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => self::iframeAttributes($settings),
      ];

      // Iframe is removed on lazyloaded, puts data at non-removable storage.
      $variables['attributes']['data-media'] = Json::encode(['type' => $settings['type']]);
    }
  }

  /**
   * Provides (Responsive) image noscript if so configured.
   */
  public static function buildNoscriptImage(array &$variables): void {
    $settings = $variables['settings'];
    $blazies = &$settings['blazies'];
    $noscript = $variables['image'];
    $noscript['#uri'] = $blazies->get('resimage.id') ? $settings['uri'] : $settings['image_url'];
    $noscript['#attributes']['data-b-noscript'] = TRUE;

    $variables['noscript'] = [
      '#type' => 'inline_template',
      '#template' => '{{ prefix | raw }}{{ noscript }}{{ suffix | raw }}',
      '#context' => [
        'noscript' => $noscript,
        'prefix' => '<noscript>',
        'suffix' => '</noscript>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function lazyAttributes(array &$attributes, array $settings = []): void {
    // For consistent CSS fix, and w/o Native.
    $attributes['class'][] = $settings['lazy_class'];

    // Slick has its own class and methods: ondemand, anticipative, progressive.
    // The data-[SRC|SCRSET|LAZY] is if `nojs` disabled, background, or video.
    if (!$settings['unlazy']) {
      $attributes['data-' . $settings['lazy_attribute']] = $settings['image_url'];
    }
  }

  /**
   * Removes loading attributes if so configured.
   */
  public static function unloading(array &$attributes, array &$settings): void {
    $blazies = &$settings['blazies'];
    $flag = $blazies->get('is.unloading');
    $flag = $flag || $blazies->get('is.slider') && $settings['delta'] == $blazies->get('initial');

    if ($flag) {
      $attributes['data-b-unloading'] = TRUE;
    }
  }

  /**
   * Provide common attributes for IMG, IFRAME, VIDEO, DIV, etc. elements.
   */
  public static function commonAttributes(array &$attributes, array $settings = []): void {
    $attributes['class'][] = 'media__element';

    // @todo at 2022/2 core has no loading Responsive.
    $excludes = in_array($settings['loading'], ['slider', 'unlazy']);
    if (!empty($settings['width']) && !$excludes) {
      $attributes['loading'] = $settings['loading'] ?: 'lazy';
    }
  }

  /**
   * Modifies container attributes with aspect ratio for iframe, image, etc.
   */
  public static function aspectRatioAttributes(array &$attributes, array &$settings): void {
    $blazies = &$settings['blazies'];
    $settings['ratio'] = str_replace(':', '', $settings['ratio']);

    // Fixed aspect ration is taken care of by pure CSS. Fluid means dynamic.
    if ($settings['height'] && $blazies->get('is.fluid')) {
      // If "lucky", Blazy/ Slick Views galleries may already set this once.
      // Lucky when you don't flatten out the Views output earlier.
      $padding = round((($settings['height'] / $settings['width']) * 100), 2);
      $padding = $blazies->get('item.padding_bottom', $padding);

      self::inlineStyle($attributes, 'padding-bottom: ' . $padding . '%;');

      // Views rewrite results or Twig inline_template may strip out `style`
      // attributes, provide hint to JS.
      $attributes['data-ratio'] = $padding;
    }
  }

  /**
   * Provides container attributes for .blazy container: .field, .view, etc.
   */
  public static function containerAttributes(array &$attributes, array $settings = []): void {
    $settings += ['namespace' => 'blazy'];
    if (!isset($settings['blazies'])) {
      $settings += BlazyDefault::htmlSettings();
    }

    $classes = empty($attributes['class']) ? [] : $attributes['class'];
    $attributes['data-blazy'] = empty($settings['blazy_data']) ? '' : Json::encode($settings['blazy_data']);

    // Provides data-LIGHTBOX-gallery to not conflict with original modules.
    if (!empty($settings['media_switch']) && $settings['media_switch'] != 'content') {
      $switch = str_replace('_', '-', $settings['media_switch']);
      $attributes['data-' . $switch . '-gallery'] = TRUE;
      $classes[] = 'blazy--' . $switch;
    }

    // For CSS fixes.
    if ($settings['unlazy']) {
      $classes[] = 'blazy--nojs';
    }

    // Provides contextual classes relevant to the container: .field, or .view.
    // Sniffs for Views to allow block__no_wrapper, views__no_wrapper, etc.
    foreach (['field', 'view'] as $key) {
      if (!empty($settings[$key . '_name'])) {
        $name = str_replace('_', '-', $settings[$key . '_name']);
        $name = $key == 'view' ? 'view--' . $name : $name;
        $classes[] = $settings['namespace'] . '--' . $key;
        $classes[] = $settings['namespace'] . '--' . $name;
        if (!empty($settings['current_view_mode'])) {
          $view_mode = str_replace('_', '-', $settings['current_view_mode']);
          $classes[] = $settings['namespace'] . '--' . $name . '--' . $view_mode;
        }
      }
    }

    $attributes['class'] = array_merge(['blazy'], $classes);
  }

  /**
   * Returns the trusted HTML ID of a single instance.
   */
  public static function getHtmlId($string = 'blazy', $id = ''): string {
    if (!isset(static::$blazyId)) {
      static::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($string . '-' . ++static::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Modifies inline style to not nullify others.
   */
  public static function inlineStyle(array &$attributes, $css): void {
    $attributes['style'] = ($attributes['style'] ?? '') . $css;
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function streamWrapperManager() {
    return self::service('stream_wrapper_manager');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function routeMatch() {
    return \Drupal::routeMatch();
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function requestStack() {
    return self::service('request_stack');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function pathResolver() {
    return self::service('extension.path.resolver');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   *
   * @see https://www.drupal.org/node/2940031
   */
  public static function fileUrlGenerator() {
    return self::service('file_url_generator');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function breakpointManager() {
    return self::service('breakpoint.manager');
  }

  /**
   * Returns the commonly used path, or just the base path.
   *
   * @todo remove drupal_get_path check when min D9.3.
   */
  public static function getPath($type, $name, $absolute = FALSE): string {
    $function = 'drupal_get_path';
    if ($resolver = self::pathResolver()) {
      $path = $resolver->getPath($type, $name);
    }
    else {
      $path = is_callable($function) ? $function($type, $name) : '';
    }
    return $absolute ? \base_path() . $path : $path;
  }

  /**
   * Checks if Blazy is in CKEditor preview mode where no JS assets are loaded.
   */
  public static function isPreview(): bool {
    if (!isset(static::$isPreview)) {
      static::$isPreview = self::isAmp() || self::isSandboxed();
    }
    return static::$isPreview;
  }

  /**
   * Checks if Blazy is in AMP pages.
   */
  public static function isAmp(): bool {
    if (!isset(static::$isAmp)) {
      $stack = self::requestStack();
      static::$isAmp = $stack && $stack->getCurrentRequest()->query->get('amp');
    }
    return static::$isAmp;
  }

  /**
   * In CKEditor without JS assets, interactive elements must be sandboxed.
   */
  public static function isSandboxed(): bool {
    if (!isset(static::$isSandboxed)) {
      $route = self::routeMatch()->getRouteName();
      $check = FALSE;

      // @todo remove after regression fixes, or keep it due to thumbnail sizes.
      $edits = ['entity_browser.', 'edit_form', 'add_form', '.preview'];
      foreach ($edits as $key) {
        if (mb_strpos($route, $key) !== FALSE) {
          $check = TRUE;
          break;
        }
      }
      static::$isSandboxed = $check;
    }
    return static::$isSandboxed;
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(array &$definitions, $formatter = 'blazy_base', array $settings = []): void {
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   */
  public static function root($container) {
    return version_compare(\Drupal::VERSION, '9.0', '<') ? $container->get('app.root') : $container->getParameter('app.root');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Returns URI from image item.
   *
   * @todo deprecated and removed for BlazyFile::uri().
   */
  public static function uri($item): string {
    return BlazyFile::uri($item);
  }

  /**
   * Returns fake image item based on the given $attributes.
   *
   * @todo deprecated and removed for BlazyFile::image().
   */
  public static function image(array $attributes = []) {
    return BlazyFile::image($attributes);
  }

}
