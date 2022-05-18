<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common shared methods across Blazy ecosystem to DRY.
 */
abstract class BlazyManagerBase implements BlazyManagerInterface {

  // Fixed for EB AJAX issue: #2893029.
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The app root.
   *
   * @var \SplString
   */
  protected $root;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Static cache for the lightboxes.
   *
   * @var array
   */
  protected $lightboxes;

  /**
   * Constructs a BlazyManager object.
   */
  public function __construct($root, EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache) {
    $this->root              = $root;
    $this->entityRepository  = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler     = $module_handler;
    $this->renderer          = $renderer;
    $this->configFactory     = $config_factory;
    $this->cache             = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      Blazy::root($container),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );

    // @todo remove and use DI at 2.x+ post sub-classes updates.
    $instance->setLanguageManager($container->get('language_manager'));
    return $instance;
  }

  /**
   * Returns the app root.
   */
  public function root() {
    return $this->root;
  }

  /**
   * Returns the language manager service.
   */
  public function languageManager() {
    return $this->languageManager;
  }

  /**
   * Sets the language manager service.
   *
   * @todo remove and use DI at 3.x+ post sub-classes updates.
   */
  public function setLanguageManager($language_manager) {
    $this->languageManager = $language_manager;
    return $this;
  }

  /**
   * Returns the entity repository service.
   */
  public function getEntityRepository() {
    return $this->entityRepository;
  }

  /**
   * Returns the entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Returns the module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

  /**
   * Returns the renderer.
   */
  public function getRenderer() {
    return $this->renderer;
  }

  /**
   * Returns the config factory.
   */
  public function getConfigFactory() {
    return $this->configFactory;
  }

  /**
   * Returns the cache.
   */
  public function getCache() {
    return $this->cache;
  }

  /**
   * Returns any config, or keyed by the $setting_name.
   */
  public function configLoad($setting_name = '', $settings = 'blazy.settings') {
    $config  = $this->configFactory->get($settings);
    $configs = $config->get();
    unset($configs['_core']);
    return empty($setting_name) ? $configs : $config->get($setting_name);
  }

  /**
   * Returns a shortcut for loading a config entity: image_style, slick, etc.
   */
  public function entityLoad($id, $entity_type = 'image_style') {
    return $this->entityTypeManager->getStorage($entity_type)->load($id);
  }

  /**
   * Returns a shortcut for loading multiple configuration entities.
   */
  public function entityLoadMultiple($entity_type = 'image_style', $ids = NULL) {
    return $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $attach = []) {
    $this->getCommonSettings($attach);
    $blazies = $attach['blazies'];
    $unblazy = $blazies->get('is.unblazy', FALSE);
    $unload = $blazies->get('ui.nojs.lazy', FALSE);
    $switch = $attach['media_switch'] ?? '';
    $load = [];

    if ($switch && $switch != 'content') {
      $attach[$switch] = $switch;

      BlazyLightbox::attach($load, $attach);
    }

    // Allow variants of grid, columns, flexbox, native grid to co-exist.
    if ($attach['style']) {
      $attach[$attach['style']] = $attach['style'];
    }

    // Always keep Drupal UI config to support dynamic compat features.
    $config = $this->configLoad('blazy');
    $config['loader'] = !$unload;
    $config['unblazy'] = $unblazy;

    // One is enough due to various formatters negating each others.
    $compat = $blazies->get('libs.compat');

    // Only if `No JavaScript` option is disabled, or has compat.
    // Compat is a loader for Blur, BG, Video which Native doesn't support.
    if ($compat || !$unload) {
      if ($compat) {
        $config['compat'] = $compat;
      }

      // Modern sites may want to forget oldies, respect.
      if (!$unblazy) {
        $load['library'][] = 'blazy/blazy';
      }

      foreach (BlazyDefault::nojs() as $key) {
        if (empty($blazies->get('ui.nojs.' . $key))) {
          $lib = $key == 'lazy' ? 'load' : $key;
          $load['library'][] = 'blazy/' . $lib;
        }
      }
    }

    $load['drupalSettings']['blazy'] = $config;
    $load['drupalSettings']['blazyIo'] = $this->getIoSettings($attach);

    foreach (BlazyDefault::components() as $component) {
      if ($blazies->get('libs.' . $component, FALSE) || !empty($attach[$component])) {
        $load['library'][] = 'blazy/' . $component;
      }
    }

    // Adds AJAX helper to revalidate Blazy/ IO, if using VIS, or alike.
    if ($blazies->get('use.ajax', FALSE)) {
      $load['library'][] = 'blazy/bio.ajax';
    }

    // Preload.
    if (!empty($attach['preload'])) {
      BlazyFile::preload($load, $attach);
    }

    $this->moduleHandler->alter('blazy_attach', $load, $attach);
    return $load;
  }

  /**
   * {@inheritdoc}
   */
  public function getIoSettings(array $attach = []) {
    $io = [];
    $thold = trim($this->configLoad('io.threshold') ?? "");
    $thold = str_replace(['[', ']'], '', $thold ?: '0');

    // @todo re-check, looks like the default 0 is broken sometimes.
    if ($thold == '0') {
      $thold = '0, 0.25, 0.5, 0.75, 1';
    }

    $thold = strpos($thold, ',') !== FALSE ? array_map('trim', explode(',', $thold)) : [$thold];
    $formatted = [];
    foreach ($thold as $value) {
      $formatted[] = strpos($value, '.') !== FALSE ? (float) $value : (int) $value;
    }

    // Respects hook_blazy_attach_alter() for more fine-grained control.
    foreach (['disconnect', 'rootMargin', 'threshold'] as $key) {
      $default = $key == 'rootMargin' ? '0px' : FALSE;
      $value = $key == 'threshold' ? $formatted : $this->configLoad('io.' . $key);
      $io[$key] = $attach['io.' . $key] ?? ($value ?: $default);
    }

    return (object) $io;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareData(array &$build, $entity = NULL): void {
    // Do nothing, let extenders share data at ease as needed.
  }

  /**
   * Returns the common UI settings inherited down to each item.
   *
   * The `fx` sequence: hook_alter > formatters (not implemented yet) > UI.
   * The `_fx` is a special flag such as to temporarily disable till needed.
   * Called by field formatters, views [styles|fields via BlazyEntity],
   * [blazy|splide|slick] filters.
   */
  public function getCommonSettings(array &$settings = []) {
    $config = array_intersect_key($this->configLoad(), BlazyDefault::uiSettings());
    $config['fx'] = $config['fx'] ?? '';
    $config['fx'] = empty($settings['fx']) ? $config['fx'] : $settings['fx'];

    // @todo remove merge once migrated to BlazySettings instance.
    // @todo revert $settings = array_merge($settings, $config);
    $settings += BlazyDefault::htmlSettings();
    $blazies = &$settings['blazies'];
    $switch = $settings['media_switch'];
    $iframe_domain = $this->configLoad('iframe_domain', 'media.settings');
    $lightboxes = $this->getLightboxes();

    // @todo remove some settings for `blazies` after sub-module updates.
    // @todo some plugin requires setting name by its name: blur, compat, etc.
    $settings['fx'] = $fx = $settings['_fx'] ?? $config['fx'];
    $is_blur = $fx == 'blur';
    $settings['lightbox'] = $lightbox = ($switch && in_array($switch, $lightboxes)) ? $switch : $settings['lightbox'];
    $settings['loading'] = $settings['loading'] ?: 'lazy';
    $settings['route_name'] = $route_name = $this->getRouteName();
    $settings['_resimage'] = $settings['_resimage'] ?: $this->moduleHandler->moduleExists('responsive_image');

    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    $is_preview = $settings['is_preview'] = Blazy::isPreview();
    $is_amp = Blazy::isAmp();
    $is_sandboxed = Blazy::isSandboxed();
    $is_bg = !empty($settings['background']);
    $is_unload = !empty($config['nojs']['lazy']);
    $is_slider = $settings['loading'] == 'slider';
    $is_unloading = $settings['loading'] == 'unlazy';
    $is_defer = $settings['loading'] == 'defer';
    $is_fluid = $settings['ratio'] == 'fluid';
    $is_static = $is_preview || $is_amp || $is_sandboxed;
    $is_undata = $is_static || $is_unloading;
    $is_nojs = $is_unload || $is_undata;
    $is_video = $settings['bundle'] == 'video' || in_array('video', $settings['bundles'] ?? []);

    // When `defer` is chosen, overrides global `No JavaScript: lazy`, ensures
    // to not affect AMP, CKEditor, or other preview pages where nojs is a must.
    if ($is_nojs && $is_defer) {
      $is_nojs = $is_undata;
    }

    // Compat is anything that Native lazy doesn't support.
    $is_compat = $fx
      || $is_bg
      || $is_fluid
      || $is_video
      || $is_defer
      || $blazies->get('libs.compat');

    // Some should be refined per item against potential mixed media items.
    $blazies->set('ui', $config)
      ->set('is.amp', $is_amp)
      ->set('is.fluid', $is_fluid)
      ->set('is.nojs', $is_nojs)
      ->set('is.preview', $is_preview)
      ->set('is.sandboxed', $is_sandboxed)
      ->set('is.slider', $is_slider)
      ->set('is.static', $is_static)
      ->set('is.unblazy', $this->configLoad('io.unblazy'))
      ->set('is.undata', $is_undata)
      ->set('is.unload', $is_unload)
      ->set('is.unloading', $is_unloading)
      ->set('libs.animate', $fx)
      ->set('libs.background', $is_bg)
      ->set('libs.blur', $is_blur)
      ->set('libs.compat', $is_compat)
      ->set('current_language', $current_language)
      ->set('fx', $fx)
      ->set('iframe_domain', $iframe_domain)
      ->set('lightbox', $lightbox)
      ->set('lightboxes', $lightboxes)
      ->set('route_name', $route_name)
      ->set('use.dataset', $is_bg);

    // Allows lightboxes to provide its own optionsets, e.g.: ElevateZoomPlus.
    if ($switch) {
      $settings[$switch] = $feature = empty($settings[$switch]) ? $switch : $settings[$switch];
      $blazies->set($feature, $feature);
    }

    // Checks for [Responsive] image styles.
    BlazyFile::imageStyles($settings);

    // Formatters, Views style, not Filters.
    if (!empty($settings['style'])) {
      BlazyGrid::toNativeGrid($settings);
    }

    // Lazy load types: blazy, and slick: ondemand, anticipated, progressive.
    $settings['blazy'] = !empty($settings['blazy']) || $is_bg || $blazies->get('resimage.style');
    $lazy = $settings['blazy'] ? 'blazy' : ($settings['lazy'] ?? '');
    $settings['lazy'] = $is_nojs ? '' : $lazy;

    // @todo re-check after sub-modules which were only aware of `is_preview`.
    // Basically tricking overrides by the reversed name due to sub-modules are
    // not updated to the new options `No JavaScript` + `Loading priority`, yet.
    // As known, Splide/ Slick have their own lazy, but might break till further
    // updates. Choosing Blazy as their lazyload method is the solution to be
    // compatible with the mentioned options. Better than sacrificing Native.
    $settings['unlazy'] = empty($settings['lazy']);
  }

  /**
   * Returns the common settings extracted from the given entity.
   */
  public function getEntitySettings(array &$settings, $entity) {
    $blazies = &$settings['blazies'];
    $internal_path = $absolute_path = NULL;

    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew()) {
      try {
        $lang = $blazies->get('current_language');
        // Check if multilingual is enabled (@see #3214002).
        if ($entity->hasTranslation($lang)) {
          // Load the translated url.
          $url = $entity->getTranslation($lang)->toUrl();
        }
        else {
          // Otherwise keep the standard url.
          $url = $entity->toUrl();
        }

        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
      catch (\Exception $ignore) {
        // Do nothing.
      }
    }

    // @todo group some non-ui settings into `blazies`.
    // @todo Remove checks after another check, in case already set somewhere.
    // The `current_view_mode` (entity|views display) is not `view_mode` option.
    $settings['current_view_mode'] = empty($settings['current_view_mode']) ? '_custom' : $settings['current_view_mode'];
    $settings['entity_id'] = empty($settings['entity_id']) ? $entity->id() : $settings['entity_id'];
    $settings['entity_type_id'] = empty($settings['entity_type_id']) ? $entity->getEntityTypeId() : $settings['entity_type_id'];
    $settings['bundle'] = empty($settings['bundle']) ? $entity->bundle() : $settings['bundle'];
    $settings['content_url'] = $settings['absolute_path'] = $absolute_path;
    $settings['internal_path'] = $internal_path;
    $settings['cache_metadata']['keys'][] = $settings['entity_id'];
    $settings['cache_metadata']['keys'][] = $entity->getRevisionID();
  }

  /**
   * {@inheritdoc}
   */
  public function getLightboxes() {
    if (!isset($this->lightboxes)) {
      $cid = 'blazy_lightboxes';

      if ($cache = $this->cache->get($cid)) {
        $this->lightboxes = $cache->data;
      }
      else {
        $lightboxes = [];
        foreach (['colorbox', 'photobox'] as $lightbox) {
          if (function_exists($lightbox . '_theme')) {
            $lightboxes[] = $lightbox;
          }
        }

        $paths = [
          'photobox' => 'photobox/photobox/jquery.photobox.js',
          'mfp' => 'magnific-popup/dist/jquery.magnific-popup.min.js',
        ];

        foreach ($paths as $key => $path) {
          if (is_file($this->root . '/libraries/' . $path)) {
            $lightboxes[] = $key;
          }
        }

        $this->moduleHandler->alter('blazy_lightboxes', $lightboxes);
        $lightboxes = array_unique($lightboxes);
        sort($lightboxes);

        $count = count($lightboxes);
        $tags = Cache::buildTags($cid, ['count:' . $count]);
        $this->cache->set($cid, $lightboxes, Cache::PERMANENT, $tags);

        $this->lightboxes = $lightboxes;
      }
    }
    return $this->lightboxes;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageEffects() {
    $effects[] = 'blur';

    $this->moduleHandler->alter('blazy_image_effects', $effects);
    $effects = array_unique($effects);
    return array_combine($effects, $effects);
  }

  /**
   * {@inheritdoc}
   */
  public function isBlazy(array &$settings, array $item = []) {
    // Retrieves Blazy formatter related settings from within Views style.
    $item_id = $settings['item_id'] ?? 'x';
    $content = $item[$item_id] ?? $item;
    $image   = $item['item'] ?? NULL;

    // 1. Blazy formatter within Views fields by supported modules.
    $settings['_item'] = $image;
    if (isset($item['settings'])) {
      BlazyUtil::isBlazyFormatter($settings, $item);
    }

    // 2. Blazy Views fields by supported modules.
    // Prevents edge case with unexpected flattened Views results which is
    // normally triggered by checking "Use field template" option.
    if (is_array($content) && ($view = ($content['#view'] ?? NULL))) {
      if ($blazy_field = BlazyViews::viewsField($view)) {
        $settings = array_merge(array_filter($blazy_field->mergedViewsSettings()), array_filter($settings));
      }
    }

    unset($settings['first_image']);
  }

  /**
   * Return the cache metadata common for all blazy-related modules.
   */
  public function getCacheMetadata(array $build = []) {
    $settings          = $build['settings'] ?? $build;
    $namespace         = $settings['namespace'] ?? 'blazy';
    $max_age           = $this->configLoad('cache.page.max_age', 'system.performance');
    $max_age           = empty($settings['cache']) ? $max_age : $settings['cache'];
    $id                = $settings['id'] ?? Blazy::getHtmlId($namespace);
    $suffixes[]        = empty($settings['count']) ? count(array_filter($settings)) : $settings['count'];
    $cache['tags']     = Cache::buildTags($namespace . ':' . $id, $suffixes, '.');
    $cache['contexts'] = ['languages'];
    $cache['max-age']  = $max_age;
    $cache['keys']     = $settings['cache_metadata']['keys'] ?? [$id];

    if (!empty($settings['cache_tags'])) {
      $cache['tags'] = Cache::mergeTags($cache['tags'], $settings['cache_tags']);
    }

    return $cache;
  }

  /**
   * Returns the thumbnail image using theme_image(), or theme_image_style().
   */
  public function getThumbnail(array $settings = [], $item = NULL) {
    if ($uri = ($settings['uri'] ?? NULL)) {
      $external = UrlHelper::isExternal($uri);
      $style = $settings['thumbnail_style'] ?? NULL;

      return [
        '#theme'      => $external ? 'image' : 'image_style',
        '#style_name' => $style ?: 'thumbnail',
        '#uri'        => $uri,
        '#item'       => $item,
        '#alt'        => $item && $item instanceof ImageItem ? $item->getValue()['alt'] : '',
      ];
    }
    return [];
  }

  /**
   * Provides alterable display styles.
   */
  public function getStyles() {
    $styles = [
      'column' => 'CSS3 Columns',
      'grid' => 'Grid Foundation',
      'flex' => 'Flexbox Masonry',
      'nativegrid' => 'Native Grid',
    ];
    $this->moduleHandler->alter('blazy_style', $styles);
    return $styles;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return Blazy::routeMatch()->getRouteName();
  }

  /**
   * Provides attachments and cache common for all blazy-related modules.
   */
  protected function setAttachments(array &$element, array $settings, array $attachments = []) {
    $cache                = $this->getCacheMetadata($settings);
    $attached             = $this->attach($settings);
    $attachments          = empty($attachments) ? $attached : NestedArray::mergeDeep($attached, $attachments);
    $element['#attached'] = empty($element['#attached']) ? $attachments : NestedArray::mergeDeep($element['#attached'], $attachments);
    $element['#cache']    = empty($element['#cache']) ? $cache : NestedArray::mergeDeep($element['#cache'], $cache);
  }

  /**
   * Collects defined skins as registered via hook_MODULE_NAME_skins_info().
   *
   * @todo remove for sub-modules own skins as plugins at blazy:8.x-2.1+.
   * @see https://www.drupal.org/node/2233261
   * @see https://www.drupal.org/node/3105670
   */
  public function buildSkins($namespace, $skin_class, $methods = []) {
    return [];
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::dimensions() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function setResponsiveImageDimensions(array &$settings = [], $initial = TRUE) {
    BlazyResponsiveImage::dimensions($settings, $initial);
  }

  /**
   * Deprecated method.
   *
   * @deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::getStyles() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getResponsiveImageStyles($responsive) {
    return BlazyResponsiveImage::getStyles($responsive);
  }

}
