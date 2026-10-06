<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The cache and the logs where they can be written.
 *
 * The release is mounted read-only while the site runs, so var/ cannot be
 * written — not by a request, and not by `cache:clear` in a deploy step. The
 * one writable directory a PHP project is given is VALLIC_PRIVATE_DIR
 * (`private/` at the root of the release), which is never served and outlives
 * every release.
 *
 * https://docs.vallic.com/framework-symfony#var-and-cache-directories
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        return $this->writableDir('cache');
    }

    public function getLogDir(): string
    {
        return $this->writableDir('log');
    }

    private function writableDir(string $name): string
    {
        $base = getenv('VALLIC_PRIVATE_DIR') ?: $this->getProjectDir() . '/private';
        $path = sprintf('%s/symfony/%s/%s', $base, $this->environment, $name);

        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }
}
