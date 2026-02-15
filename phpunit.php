<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

date_default_timezone_set('UTC');

// 1. Setup Container
$container = new Container;
Container::setInstance($container);
Facade::setFacadeApplication($container);

// 2. Bind 'config' with a robust ArrayAccess mock
$container->singleton('config', function () {
  return new class implements ArrayAccess {
    protected $items = [
      'html' => [
        'theme' => null,
        'themes' => [],
      ],
      'database' => [
        'connections' => [],
        'default' => 'default',
        'fetch' => 5, // PDO::FETCH_OBJ
      ],
    ];

    public function get($key, $default = null)
    {
      $data = $this->items;
      foreach (explode('.', $key) as $segment) {
        if (!is_array($data) || !array_key_exists($segment, $data)) {
          return $default;
        }
        $data = $data[$segment];
      }
      return $data;
    }

    public function set($key, $value = null)
    {
      $keys = is_array($key) ? $key : [$key => $value];
      foreach ($keys as $k => $v) {
        // Basic dot notation support for set if needed
        if (strpos($k, '.') !== false) {
          $parts = explode('.', $k);
          $target = &$this->items;
          foreach ($parts as $part) {
            if (!isset($target[$part])) $target[$part] = [];
            $target = &$target[$part];
          }
          $target = $v;
        } else {
          $this->items[$k] = $v;
        }
      }
    }

    public function offsetExists($offset): bool
    {
      return isset($this->items[$offset]);
    }
    public function offsetGet($offset): mixed
    {
      return $this->items[$offset] ?? null;
    }
    public function offsetSet($offset, $value): void
    {
      $this->items[$offset] = $value;
    }
    public function offsetUnset($offset): void
    {
      unset($this->items[$offset]);
    }
  };
});

// 3. Define fallback config helper
if (!function_exists('config')) {
  function config($key = null, $default = null)
  {
    $container = Container::getInstance();
    if (!$container->bound('config')) return $default;

    $config = $container->make('config');

    if (is_null($key)) return $config;
    if (is_array($key)) return $config->set($key);

    return $config->get($key, $default);
  }
}

// 4. Setup Capsule/Database
$capsule = new Capsule($container);
$capsule->addConnection([
  'driver'   => 'sqlite',
  'database' => ':memory:',
]);
$capsule->setEventDispatcher(new Dispatcher($container));
$capsule->setAsGlobal();
$capsule->bootEloquent();

$capsule->schema()->dropIfExists('models');
$capsule->schema()->create('models', function (\Illuminate\Database\Schema\Blueprint $table) {
  $table->increments('id');
  $table->string('string');
  $table->string('email');
  $table->timestamps();
});
