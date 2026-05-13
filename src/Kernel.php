<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Skip loading debug.yaml / web_profiler.yaml when their bundles are not
     * installed (e.g. after "composer install --no-dev" on PHP 8.2).
     */
    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $configDir = $this->getConfigDir();
        $packagesDir = $configDir.'/packages';

        $skip = [];
        if (!class_exists(\Symfony\Bundle\DebugBundle\DebugBundle::class)) {
            $skip['debug.yaml'] = true;
        }
        if (!class_exists(\Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class)) {
            $skip['web_profiler.yaml'] = true;
        }

        foreach (array_merge(glob($packagesDir.'/*.php') ?: [], glob($packagesDir.'/*.yaml') ?: []) as $file) {
            if (isset($skip[basename($file)])) {
                continue;
            }
            $container->import($file);
        }
        $envDir = $packagesDir.'/'.$this->environment;
        if (is_dir($envDir)) {
            foreach (array_merge(glob($envDir.'/*.php') ?: [], glob($envDir.'/*.yaml') ?: []) as $file) {
                $container->import($file);
            }
        }

        if (is_file($configDir.'/services.yaml')) {
            $container->import($configDir.'/services.yaml');
            $container->import($configDir.'/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import($configDir.'/{services}.php');
            $container->import($configDir.'/{services}_'.$this->environment.'.php');
        }
    }

    /**
     * Skip loading web_profiler.yaml when WebProfilerBundle is not installed.
     */
    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = $this->getConfigDir();
        $routesDir = $configDir.'/routes';

        $envRoutesDir = $routesDir.'/'.$this->environment;
        if (is_dir($envRoutesDir)) {
            foreach (array_merge(glob($envRoutesDir.'/*.php') ?: [], glob($envRoutesDir.'/*.yaml') ?: []) as $file) {
                $routes->import($file);
            }
        }

        $skip = [];
        if (!class_exists(\Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class)) {
            $skip['web_profiler.yaml'] = true;
        }
        foreach (array_merge(glob($routesDir.'/*.php') ?: [], glob($routesDir.'/*.yaml') ?: []) as $file) {
            if (isset($skip[basename($file)])) {
                continue;
            }
            $routes->import($file);
        }

        if (is_file($configDir.'/routes.yaml')) {
            $routes->import($configDir.'/routes.yaml');
        } else {
            $routes->import($configDir.'/{routes}.php');
        }

        if (false !== ($fileName = (new \ReflectionObject($this))->getFileName())) {
            $routes->import($fileName, 'attribute');
        }
    }
}
