<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Site\Settings;
use Drupal\image\Entity\ImageStyle;
use Drupal\blazy\Blazy;

/**
 * Provides file_BLAH BC for D8 - D10+ till D11 rules.
 *
 * @todo remove deprecated functions post D11, not D10, and when D8 is dropped.
 */
class BlazyFile {

  /**
   * The image style ID.
   *
   * @var array
   */
  private static $styleId;

  /**
   * Determines whether the URI has a valid scheme for file API operations.
   *
   * @param string $uri
   *   The URI to be tested.
   *
   * @return bool
   *   TRUE if the URI is valid.
   */
  public static function isValidUri($uri): bool {
    if (!empty($uri) && $manager = Blazy::streamWrapperManager()) {
      return $manager->isValidUri($uri);
    }
    return FALSE;
  }

  /**
   * Creates an relative or absolute web-accessible URL string.
   *
   * @param string $uri
   *   The file uri.
   * @param bool $relative
   *   Whether to return an relative or absolute URL.
   *
   * @return string
   *   Returns an absolute web-accessible URL string.
   */
  public static function createUrl($uri, $relative = FALSE): string {
    if ($gen = Blazy::fileUrlGenerator()) {
      // @todo recheck ::generateAbsoluteString doesn't return web-accessible
      // protocol as expected.
      return $relative ? $gen->generateString($uri) : $gen->generateAbsoluteString($uri);
    }

    $function = 'file_create_url';
    return is_callable($function) ? $function($uri) : '';
  }

  /**
   * Transforms an absolute URL of a local file to a relative URL.
   *
   * Blazy Filter or OEmbed may pass mixed (external) URI upstream.
   *
   * @param string $uri
   *   The file uri.
   * @param object $style
   *   The optional image style instance.
   * @param array $options
   *   The options: default url, sanitize.
   *
   * @return string
   *   Returns an absolute URL of a local file to a relative URL.
   *
   * @see BlazyOEmbed::getExternalImageItem()
   * @see BlazyFilter::getImageItemFromImageSrc()
   *
   * @todo make it more robust.
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    $url = $trusted_url = $options['url'] ?? '';
    $sanitize = $options['sanitize'] ?? FALSE;

    if (empty($uri)) {
      return $url;
    }

    // Returns as is if an external URL.
    if (UrlHelper::isExternal($uri)) {
      $url = $uri;
    }
    elseif (empty($trusted_url) && self::isValidUri($uri)) {
      $url = $style ? $style->buildUrl($uri) : self::createUrl($uri);

      if ($gen = Blazy::fileUrlGenerator()) {
        $url = $gen->transformRelative($url);
      }
      else {
        $function = 'file_url_transform_relative';
        $url = is_callable($function) ? $function($url) : '';
      }
    }

    // If transform failed, returns default URL, or URI as is.
    $url = $url ?: $uri;

    // Just in case, an attempted kidding gets in the way, relevant for UGC.
    if ($sanitize) {
      // @todo re-check to completely remove data URI.
      $data_uri = mb_substr($url, 0, 10) === 'data:image';
      if (!$data_uri) {
        $url = UrlHelper::stripDangerousProtocols($url);
      }
    }

    return $url ?: '';
  }

  /**
   * Returns the URI from the given image URL, relevant for unmanaged files.
   *
   * @todo re-check if core has this type of conversion.
   */
  public static function buildUri($url): ?string {
    if (!UrlHelper::isExternal($url) && $normal_path = UrlHelper::parse($url)['path']) {
      // If the request has a base path, remove it from the beginning of the
      // normal path as it should not be included in the URI.
      $base_path = \Drupal::request()->getBasePath();
      if ($base_path && mb_strpos($normal_path, $base_path) === 0) {
        $normal_path = str_replace($base_path, '', $normal_path);
      }

      $public_path = Settings::get('file_public_path', 'sites/default/files');

      // Only concerns for the correct URI, not image URL which is already being
      // displayed via SRC attribute. Don't bother language prefixes for IMG.
      if ($public_path && mb_strpos($normal_path, $public_path) !== FALSE) {
        $rel_path = str_replace($public_path, '', $normal_path);
        return Blazy::streamWrapperManager()->normalizeUri($rel_path);
      }
    }
    return NULL;
  }

  /**
   * Extracts uris from file/ media entity.
   *
   * @todo merge urls here as well once puzzles are solved: URI may be fed by
   * field formatters like this, blazy_filter, or manual call.
   */
  public static function urisFromField(array &$settings, $items, array $entities = []): array {
    $blazies = &$settings['blazies'];
    if ($uris = $blazies->get('uris')) {
      return $uris;
    }

    $func = function ($item, $entity = NULL) use (&$settings) {
      $blazies = $settings['blazies'];
      ['uri' => $uri, 'image' => $image] = self::uriAndImage($item, $entity, $settings);

      // Only needed the first found image, no problem which with mixed media.
      if (!isset($settings['_item']) || empty($settings['_item'])) {
        $settings['_item'] = $image;
        if ($uri) {
          $style = $blazies->get('image.style');
          $settings['_image_url'] = self::transformRelative($uri, $style);
        }
      }
      return $uri;
    };

    $output = [];
    foreach ($items as $key => $item) {
      // Respects empty URI to keep indices intact for correct mixed media.
      $output[] = $func($item, $entities[$key] ?? NULL);
    }

    $blazies->set('uris', $output);
    return $output;
  }

  /**
   * Returns URI and the image item, if applicable.
   */
  public static function uriAndImage($item, $entity = NULL, array $settings = []): array {
    $uri = $image = NULL;
    if (($settings['field_type'] ?? '') == 'image') {
      $image = $item;
      $uri = self::uri($item);
    }
    elseif ($entity && $entity->hasField('thumbnail') && $image = $entity->get('thumbnail')->first()) {
      if ($file = ($image->entity ?? NULL)) {
        $uri = $file->getFileUri();
      }
    }
    return ['uri' => $uri, 'image' => $image];
  }

  /**
   * Returns URI from image item.
   */
  public static function uri($item): string {
    $fallback = $item->uri ?? '';
    return empty($item) ? '' : (($file = $item->entity) && empty($item->uri) ? $file->getFileUri() : $fallback);
  }

  /**
   * Returns fake image item based on the given $attributes.
   */
  public static function image(array $attributes = []) {
    $item = new \stdClass();
    foreach (['uri', 'width', 'height', 'target_id', 'alt', 'title'] as $key) {
      if (isset($attributes[$key])) {
        $item->{$key} = $attributes[$key];
      }
    }
    return $item;
  }

  /**
   * Prepares URI, extension, image styles, lightboxes.
   *
   * Also checks if an extension should not use image style: apng svg gif, etc.
   */
  public static function prepare(array &$settings): bool {
    if (!($uri = ($settings['uri'] ?? NULL))) {
      return FALSE;
    }

    $pathinfo = pathinfo($uri);
    $settings['extension'] = $ext = $pathinfo['extension'] ?? '';
    $settings['_richbox'] = !empty($settings['colorbox']) || !empty($settings['mfp']) || !empty($settings['_richbox']);

    $blazies = &$settings['blazies'];
    $blazies->set('is.external', UrlHelper::isExternal($uri));

    $extensions = ['svg'];
    if ($unstyles = $blazies->get('ui.unstyled_extensions')) {
      $extensions = array_merge($extensions, array_map('trim', explode(' ', mb_strtolower($unstyles))));
      $extensions = array_unique($extensions);
    }

    $unstyled = $ext && in_array($ext, $extensions);

    // Disable image style if so configured.
    if ($unstyled) {
      $images = ['box', 'box_media', 'image', 'thumbnail', 'responsive_image'];
      foreach ($images as $image) {
        $settings[$image . '_style'] = '';
      }
    }

    $blazies->set('is.unstyled', $unstyled);
    return $unstyled;
  }

  /**
   * Checks for [Responsive] image styles.
   */
  public static function imageStyles(array &$settings, $multiple = FALSE): void {
    $blazy = Blazy::service('blazy.manager');
    $exist = $settings['_resimage'] ?? FALSE;
    $blazies = &$settings['blazies'];

    // Multiple is a flag for various styles: Blazy Filter, GridStack, etc.
    // While fields can only have one image style per field.
    if (!$blazies->get('resimage.style') || $multiple) {
      $style = $settings['responsive_image_style'] ?? NULL;
      $settings['_resimage'] = $applicable = $exist && $style;
      $responsive_image_style = $settings['resimage'] ?? NULL;

      if (empty($responsive_image_style) && $applicable) {
        $responsive_image_style = $blazy->entityLoad($style, 'responsive_image_style');
      }

      if ($responsive_image_style) {
        // @todo remove responsive_image_style_id.
        $id = $settings['responsive_image_style_id'] = $responsive_image_style->id();
        $styles = BlazyResponsiveImage::getStyles($responsive_image_style);

        $blazies->set('resimage.id', $id)
          ->set('resimage.caches', $styles['caches'])
          ->set('resimage.styles', $styles['styles']);
      }

      // @todo remove $settings['resimage'] for blazies after sub-modules.
      $settings['resimage'] = $responsive_image_style;
      $blazies->set('resimage.style', $responsive_image_style);
    }

    // Specific for lightbox, it can be (Responsive) image.
    foreach (['box', 'box_media', 'image', 'thumbnail'] as $key) {
      if (!$blazies->get($key . '.style') || $multiple) {
        $image_style = NULL;
        if ($style = ($settings[$key . '_style'] ?? '')) {
          if ($key == 'box' && $exist) {
            $resimage = $blazy->entityLoad($style, 'responsive_image_style');
            $blazies->set($key . '.resimage.style', $resimage)
              ->set($key . '.resimage.id', $resimage ? $resimage->id() : NULL);
          }
          $image_style = $blazy->entityLoad($style, 'image_style');
        }

        $blazies->set($key . '.style', $image_style);
      }
    }
  }

  /**
   * Provides image url based on the given settings.
   */
  public static function imageUrl(array &$settings, $style = NULL): string {
    // Provides image_url, not URI, expected by lazyload.
    $uri = $settings['uri'] ?? $settings['_uri'] ?? NULL;
    $url = '';
    if ($uri) {
      ['url' => $url, 'style' => $style] = self::imageUrlAndStyle($uri, $settings, $style);

      $settings['image_url'] = $url;

      // @todo move it out here.
      if ($style) {
        $settings['cache_tags'] = $style->getCacheTags();

        // Only re-calculate dimensions if not cropped, nor already set.
        if (empty($settings['_dimensions']) && empty($settings['responsive_image_style'])) {
          $settings = array_merge($settings, self::transformDimensions($style, $settings));
        }
      }
    }

    return $url;
  }

  /**
   * Returns image url and style based on the given settings.
   *
   * @todo merge URL into self::transformRelative.
   */
  public static function imageUrlAndStyle($uri, array $settings, $style = NULL): array {
    $blazies = &$settings['blazies'];
    // Provides image_url, not URI, expected by lazyload.
    $valid = self::isValidUri($uri);
    $styled = $valid && !$blazies->get('is.unstyled');
    $_style = $settings['image_style'] ?? '';
    $style = $style ?: $blazies->get('image.style');
    // @todo remove after another check, might be needed by non-API custom work.
    $style = $style ?: (empty($_style) ? NULL : ImageStyle::load($_style));
    $url = $settings['image_url'] ?? '';

    // Image style modifier can be multi-style images such as GridStack.
    $sanitize = !empty($settings['_check_protocol']);
    $options = ['url' => $url, 'sanitize' => $sanitize];
    $url = self::transformRelative($uri, ($styled ? $style : NULL), $options);
    $no_dims = empty($settings['height']) || empty($settings['width']);

    $ratio = $no_dims ? 100 : round((($settings['height'] / $settings['width']) * 100), 2);

    return [
      'url' => $url,
      'style' => $style,
      'ratio' => $ratio,
    ];
  }

  /**
   * Prepares CSS background image.
   *
   * @todo remove and merge it with imageUrlAndStyle.
   */
  public static function backgroundImage(array $settings, $style = NULL) {
    $no_dims = empty($settings['height']) || empty($settings['width']);
    return [
      'src' => $style ? self::transformRelative($settings['uri'], $style) : $settings['image_url'],
      'ratio' => $no_dims ? 100 : round((($settings['height'] / $settings['width']) * 100), 2),
    ];
  }

  /**
   * Builds URLs, cache tags, and dimensions for an individual image.
   *
   * Respects a few scenarios:
   * 1. Blazy Filter or unmanaged file with/ without valid URI.
   * 2. Hand-coded image_url with/ without valid URI.
   * 3. Respects first_uri without image_url such as colorbox/zoom-like.
   * 4. File API via field formatters or Views fields/ styles with valid URI.
   * If we have a valid URI, provides the correct image URL.
   * Otherwise leave it as is, likely hotlinking to external/ sister sites.
   * Hence URI validity is not crucial in regards to anything but #4.
   * The image will fail silently at any rate given non-expected URI.
   *
   * @param array $settings
   *   The given settings being modified.
   * @param object $item
   *   The image item.
   */
  public static function urlAndDimensions(array &$settings, $item = NULL): void {

    // BlazyFilter, or image style with crop, may already set these.
    self::imageDimensions($settings, $item);

    // Provides image url based on the given settings.
    self::imageUrl($settings);
  }

  /**
   * Checks lazy insanity given various features/ media types + loading option.
   *
   * @todo re-check if any misses, or regressions here.
   */
  public static function lazyOrNot(array &$settings): void {
    $blazies = &$settings['blazies'];

    // The SVG placeholder should accept either original, or styled image.
    $is_media = in_array($settings['type'], ['audio', 'video']);

    // Loading `slider` or `unlazy` is more a quasi-loading to vary logic.
    $unlazy = $blazies->get('is.slider') && $settings['delta'] == $blazies->get('initial');
    $settings['unlazy'] = $unlazy ? TRUE : $settings['unlazy'];

    // @todo remove settings.placeholder|use_media checks after sub-modules.
    $settings['placeholder'] = $placeholder = $blazies->get('ui.placeholder') ?: Placeholder::generate($settings['width'], $settings['height']);
    $use_media = ($settings['embed_url'] && $is_media) || ($settings['use_media'] ?? FALSE);

    // @todo remove use_loading after sub-module updates.
    // @todo better logic to support loader as required, must decouple loader.
    // @todo $lazy = $settings['loading'] == 'lazy';
    // @todo $lazy = !empty($settings['blazy']) && ($blazies->get('libs.compat') || $lazy);
    $use_loader = $settings['unlazy'] ? FALSE : $settings['use_loading'];
    $settings['use_loading'] = $use_loader;

    $blazies->set('use.loader', $use_loader);
    $blazies->set('use.media', $use_media);
    $blazies->set('ui.placeholder', $placeholder);
  }

  /**
   * A wrapper for ImageStyle::transformDimensions().
   *
   * @param object $style
   *   The given image style.
   * @param array $data
   *   The data settings: _width, _height, _uri, width, height, and uri.
   * @param bool $initial
   *   Whether particularly transforms once for all, or individually.
   */
  public static function transformDimensions($style, array $data, $initial = FALSE): array {
    $uri = $initial ? '_uri' : 'uri';
    $key = hash('md2', ($style->id() . $data[$uri] . $initial));

    if (!isset(static::$styleId[$key])) {
      $_width  = $initial ? '_width' : 'width';
      $_height = $initial ? '_height' : 'height';
      $width   = $data[$_width] ?? NULL;
      $height  = $data[$_height] ?? NULL;
      $dim     = ['width' => $width, 'height' => $height];

      // Funnily $uri is ignored at all core image effects.
      $style->transformDimensions($dim, $data[$uri]);

      // Sometimes they are string, cast them integer to reduce JS logic.
      if ($dim['width'] != NULL) {
        $dim['width'] = (int) $dim['width'];
      }
      if ($dim['height'] != NULL) {
        $dim['height'] = (int) $dim['height'];
      }

      static::$styleId[$key] = [
        'width' => $dim['width'],
        'height' => $dim['height'],
      ];
    }
    return static::$styleId[$key];
  }

  /**
   * Provides original unstyled image dimensions based on the given image item.
   */
  public static function imageDimensions(array &$settings, $item = NULL, $initial = FALSE): void {
    $width = $initial ? '_width' : 'width';
    $height = $initial ? '_height' : 'height';
    $uri = $initial ? '_uri' : 'uri';

    if (empty($settings[$height]) && $item) {
      $settings[$width] = $item->width ?? NULL;
      $settings[$height] = $item->height ?? NULL;
    }

    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF/ WYSIWG/ filter image without image_style.
    if (empty($settings['image_style']) && empty($settings[$height]) && !empty($settings[$uri])) {
      $abs = empty($settings['uri_root']) ? $settings[$uri] : $settings['uri_root'];
      // Must be valid URI, or web-accessible url, not: /modules|themes/...
      if (!BlazyFile::isValidUri($abs) && mb_substr($abs, 0, 1) == '/') {
        if ($request = Blazy::requestStack()) {
          $abs = $request->getCurrentRequest()->getSchemeAndHttpHost() . $abs;
        }
      }

      // Prevents 404 warning when video thumbnail missing for a reason.
      if ($data = @getimagesize($abs)) {
        [$settings[$width], $settings[$height]] = $data;
      }
    }

    // Sometimes they are string, cast them integer to reduce JS logic.
    $settings[$width] = empty($settings[$width]) ? NULL : (int) $settings[$width];
    $settings[$height] = empty($settings[$height]) ? NULL : (int) $settings[$height];
  }

  /**
   * Preload late-discovered resources for better performance.
   *
   * @see https://web.dev/preload-critical-assets/
   * @see https://caniuse.com/?search=preload
   * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Link_types/preload
   * @see https://developer.chrome.com/blog/new-in-chrome-73/#more
   * @todo support multiple hero images like carousels.
   */
  public static function preload(array &$load, array $settings = []): void {
    $blazies = &$settings['blazies'];
    $uris = $blazies->get('uris', []);
    if (empty($uris)) {
      return;
    }

    $mime = mime_content_type($uris[0]);
    [$type] = array_map('trim', explode('/', $mime, 2));

    $link = function ($url, $uri = NULL, $item = NULL) use ($mime, $type): array {
      // Each field may have different mime types for each image just like URIs.
      $mime = $uri ? mime_content_type($uri) : $mime;
      if ($item) {
        $item_type = $item['type'] ?? '';
        $mime = $item_type ? $item_type->value() : $mime;
      }

      [$type] = array_map('trim', explode('/', $mime, 2));
      $key = hash('md2', $url);

      $attrs = [
        'rel' => 'preload',
        'as' => $type,
        'href' => $url,
        'type' => $mime,
      ];

      $suffix = '';
      if ($srcset = ($item['srcset'] ?? '')) {
        $suffix = '_responsive';
        $attrs['imagesrcset'] = $srcset->value();

        if ($sizes = ($item['sizes'] ?? '')) {
          $attrs['imagesizes'] = $sizes->value();
        }
      }

      // Checks for external URI.
      if (UrlHelper::isExternal($uri ?: $url)) {
        $attrs['crossorigin'] = TRUE;
      }

      return [
        [
          '#tag' => 'link',
          '#attributes' => $attrs,
        ],
        'blazy' . $suffix . '_' . $type . $key,
      ];
    };

    $links = [];

    // Supports multiple sources.
    if ($sources = $blazies->get('resimage.sources', [])) {
      foreach ($sources as $source) {
        $url = $source['fallback'];

        // Preloading 1px data URI makes no sense, see if image_url.
        $data_uri = mb_substr($url, 0, 10) === 'data:image';
        if ($data_uri && !empty($settings['image_url'])) {
          $url = $settings['image_url'];
        }

        foreach ($source['items'] as $key => $item) {
          if (!empty($item['srcset'])) {
            $links[] = $link($url, NULL, $item);
          }
        }
      }
    }
    else {
      foreach ($uris as $uri) {
        // URI might be empty with mixed media, but indices are preserved.
        if ($uri) {
          ['url' => $url] = self::imageUrlAndStyle($uri, $settings);
          $links[] = $link($url, $uri);
        }
      }
    }

    if ($links) {
      foreach ($links as $key => $value) {
        $load['html_head'][$key] = $value;
      }
    }
  }

}
