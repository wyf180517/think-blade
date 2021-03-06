<?php

namespace HZEX\Blade;

use Illuminate\Contracts\View\View as ViewInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\FileEngine;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewFinderInterface;
use function is_dir;
use function mkdir;

/**
 * Standalone class for generating text using blade templates.
 */
class BladeInstance implements BladeInterface
{
    /**
     * @var string The default path for views.
     */
    private $path;

    /**
     * @var string The default path for cached php.
     */
    private $cache;

    /**
     * @var Factory|null The internal cache of the Factory to only instantiate it once.
     */
    private $factory;

    /**
     * @var FileViewFinder|null The internal cache of the FileViewFinder to only instantiate it once.
     */
    private $finder;

    /**
     * @var BladeCompiler|null The internal cache of the BladeCompiler to only instantiate it once.
     */
    private $compiler;

    /**
     * Create a new instance of the blade view factory.
     *
     * @param string $path  The default path for views
     * @param string $cache The default path for cached php
     */
    public function __construct(string $path, string $cache)
    {
        $this->path  = $path;
        $this->cache = $cache;
    }

    /**
     * @return EngineResolver
     */
    private function getResolver(): EngineResolver
    {
        $resolver = new EngineResolver();

        $resolver->register("blade", function () {
            $blade = $this->getCompiler();

            return new CompilerEngine($blade);
        });

        $resolver->register("file", function () {
            return new FileEngine();
        });

        $resolver->register("php", function () {
            return new PhpEngine();
        });

        return $resolver;
    }

    /**
     * Get the laravel view finder.
     *
     * @return FileViewFinder
     */
    private function getViewFinder(): ViewFinderInterface
    {
        if (!$this->finder) {
            $this->finder = new TpViewFinder();
        }

        return $this->finder;
    }

    /**
     * Get the laravel view factory.
     *
     * @return Factory
     */
    public function getViewFactory(): Factory
    {
        if ($this->factory) {
            return $this->factory;
        }

        $this->factory = new Factory($this->getResolver(), $this->getViewFinder());

        return $this->factory;
    }

    /**
     * Get the internal compiler in use.
     *
     * @return BladeCompiler
     */
    private function getCompiler(): BladeCompiler
    {
        if ($this->compiler) {
            return $this->compiler;
        }

        if (!is_dir($this->cache)) {
            mkdir($this->cache, 0777, true);
        }

        $blade = new BladeCompiler(new Filesystem(), $this->cache);

        $this->compiler = $blade;

        return $this->compiler;
    }

    /**
     * {@inheritdoc}
     * @deprecated 因查找器替换而无效
     */
    public function addExtension(string $extension): BladeInterface
    {
        $this
            ->getViewFactory()
            ->addExtension($extension, "blade");

        return $this;
    }

    /**
     * @param bool $off
     * @return $this
     */
    public function cacheDisable(bool $off)
    {
        $this->getCompiler()->setCacheDisable($off);

        return $this;
    }

    /**
     * Register a custom Blade compiler.
     *
     * @param callable $compiler
     *
     * @return $this
     */
    public function extend(callable $compiler): BladeInterface
    {
        $this
            ->getCompiler()
            ->extend($compiler);

        return $this;
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string $name
     * @param callable $handler
     *
     * @return $this
     */
    public function directive(string $name, callable $handler): BladeInterface
    {
        $this
            ->getCompiler()
            ->directive($name, $handler);

        return $this;
    }

    /**
     * Register an "if" statement directive.
     *
     * @param  string  $name
     * @param  callable  $handler
     * @return $this
     */
    public function if(string $name, callable $handler): BladeInterface
    {
        $this
            ->getCompiler()
            ->if($name, $handler);

        return $this;
    }

    /**
     * Add a path to look for views in.
     *
     * @param string $location
     * @return $this
     * @deprecated 因查找器替换而无效
     */
    public function addLocation(string $location): BladeInterface
    {
        $this->getViewFinder()->addLocation($location);

        return $this;
    }

    /**
     * Check if a view exists.
     *
     * @param string $view The name of the view to check
     *
     * @return bool
     */
    public function exists($view): bool
    {
        return $this->getViewFactory()->exists($view);
    }

    /**
     * Share data across all views.
     *
     * @param string $key The name of the variable to share
     * @param mixed $value The value to assign to the variable
     *
     * @return $this
     */
    public function share($key, $value = null): BladeInterface
    {
        $this->getViewFactory()->share($key, $value);

        return $this;
    }

    /**
     * Register a composer.
     *
     * @param string $key The name of the composer to register
     * @param mixed $value The closure or class to use
     *
     * @return array
     */
    public function composer($key, $value): array
    {
        return [];
    }

    /**
     * Register a creator.
     *
     * @param string $key The name of the creator to register
     * @param mixed $value The closure or class to use
     *
     * @return array
     */
    public function creator($key, $value): array
    {
        return [];
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param string $namespace The namespace to use
     * @param array|string $hints The hints to apply
     *
     * @return $this
     * @deprecated 因查找器替换而无效
     */
    public function addNamespace($namespace, $hints): BladeInterface
    {
        $this->getViewFactory()->addNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Replace the namespace hints for the given namespace.
     *
     * @param string $namespace The namespace to replace
     * @param array|string $hints The hints to use
     *
     * @return $this
     * @deprecated 因查找器替换而无效
     */
    public function replaceNamespace($namespace, $hints): BladeInterface
    {
        $this->getViewFactory()->replaceNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Get the evaluated view contents for the given path.
     *
     * @param string $path The path of the file to use
     * @param array $data The parameters to pass to the view
     * @param array $mergeData The extra data to merge
     *
     * @return ViewInterface The generated view
     */
    public function file($path, $data = [], $mergeData = []): ViewInterface
    {
        return $this->getViewFactory()->file($path, $data, $mergeData);
    }

    /**
     * Generate a view.
     *
     * @param string $view The name of the view to make
     * @param array $params The parameters to pass to the view
     * @param array $mergeData The extra data to merge
     *
     * @return ViewInterface The generated view
     */
    public function make($view, $params = [], $mergeData = []): ViewInterface
    {
        return $this->getViewFactory()->make($view, $params, $mergeData);
    }

    /**
     * Get the content by generating a view.
     *
     * @param string $view The name of the view to make
     * @param array $params The parameters to pass to the view
     *
     * @return string The generated content
     */
    public function render(string $view, array $params = []): string
    {
        return $this->make($view, $params)->render();
    }
}
