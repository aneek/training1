<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;

/**
 * Provides settings object.
 *
 * @todo convert settings into BlazySettings instance at blazy:3.+ if you can.
 */
class BlazySettings implements \Countable {

  /**
   * Stores the settings.
   *
   * @var \stdClass[]
   */
  protected $storage = [];

  /**
   * Creates a new BlazySettings instance.
   *
   * @param \stdClass[] $storage
   *   The storage.
   */
  public function __construct(array $storage) {
    $this->storage = $storage;
  }

  /**
   * Counts total items.
   */
  public function count(): int {
    return count($this->storage);
  }

  /**
   * Returns values from a key.
   *
   * @param string $key
   *   The storage key.
   * @param string $default_value
   *   The storage default_value.
   *
   * @return mixed
   *   A mixed value (array, string, bool, null, etc.).
   */
  public function get($key, $default_value = NULL) {
    if (empty($key)) {
      return $this->storage;
    }
    else {
      $parts = array_map('trim', explode('.', $key));
      if (count($parts) == 1) {
        return $this->storage[$key] ?? $default_value;
      }
      else {
        $value = NestedArray::getValue($this->storage, $parts, $key_exists);
        return $key_exists ? $value : $default_value;
      }
    }
  }

  /**
   * Sets values for a key.
   */
  public function set($key, $value = NULL): self {
    if (is_array($key) && !isset($value)) {
      foreach ($key as $k => $v) {
        $this->storage[$k] = $v;
      }
      return $this;
    }

    $parts = array_map('trim', explode('.', $key));
    if (count($parts) == 1) {
      $this->storage[$key] = $value;
    }
    else {
      NestedArray::setValue($this->storage, $parts, $value);
    }
    return $this;
  }

  /**
   * Merges data into a configuration object.
   *
   * @param array $data_to_merge
   *   An array containing data to merge.
   *
   * @return $this
   *   The configuration object.
   */
  public function merge(array $data_to_merge) {
    // Preserve integer keys so that configuration keys are not changed.
    $this->setData(NestedArray::mergeDeepArray([$this->storage, $data_to_merge], TRUE));
    return $this;
  }

  /**
   * Replaces the data of this configuration object.
   *
   * @param array $data
   *   The new configuration data.
   *
   * @return $this
   *   The configuration object.
   */
  public function setData(array $data) {
    $this->storage = $data;
    return $this;
  }

  /**
   * Returns the whole array.
   */
  public function storage(): array {
    return $this->storage;
  }

}
