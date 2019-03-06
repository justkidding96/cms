<?php

namespace Statamic\Assets;

use Statamic\API\Str;
use Statamic\API\URL;
use Statamic\API\Site;
use Statamic\API\YAML;
use Statamic\API\AssetContainer;
use Statamic\Assets\AssetCollection;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Assets\QueryBuilder;
use Statamic\Contracts\Assets\AssetRepository as Contract;

class AssetRepository implements Contract
{
    public function all(): AssetCollection
    {
        return collect_assets(AssetContainer::all()->flatMap(function ($container) {
            return $container->assets();
        }));
    }

    public function whereContainer(string $container): AssetCollection
    {
        return AssetContainer::find($container)->assets();
    }

    public function whereFolder(string $folder, string $container): AssetCollection
    {
        return AssetContainer::find($container)->assets($folder);
    }

    public function find(string $asset): ?Asset
    {
        return Str::contains($asset, '::')
            ? $this->findById($asset)
            : $this->findByUrl($asset);
    }

    public function findByUrl(string $url): ?Asset
    {
        // If a container can't be resolved, we'll assume there's no asset.
        if (! $container = $this->resolveContainerFromUrl($url)) {
            return null;
        }

        $siteUrl = rtrim(Site::current()->absoluteUrl(), '/');
        $containerUrl = $container->url();

        if (starts_with($containerUrl, '/')) {
            $containerUrl = $siteUrl . $containerUrl;
        }

        if (starts_with($containerUrl, $siteUrl)) {
            $url = $siteUrl . $url;
        }

        $path = str_after($url, $containerUrl);

        return $container->asset($path);
    }

    protected function resolveContainerFromUrl($url)
    {
        return AssetContainer::all()->sortBy(function ($container) {
            return strlen($container->url());
        })->first(function ($container, $id) use ($url) {
            return starts_with($url, $container->url())
                || starts_with(URL::makeAbsolute($url), $container->url());
        });
    }

    public function whereUrl($url)
    {
        return $this->findByUrl($url); // TODO: Replace usages with findByUrl
    }

    public function findById(string $id): ?Asset
    {
        list($container_id, $path) = explode('::', $id);

        // If a container can't be found, we'll assume there's no asset.
        if (! $container = AssetContainer::find($container_id)) {
            return null;
        }

        return $container->asset($path);
    }

    public function whereId($id)
    {
        return $this->findById($id); // TODO: Replace usages with findById
    }

    public function findByPath(string $path): ?Asset
    {
        return $this->all()->filter(function ($asset) use ($path) {
            return $asset->resolvedPath() === $path;
        })->first();
    }

    public function wherePath($path)
    {
        return $this->findByPath($path); // TODO: Replace usages with findByPath
    }

    public function make(): Asset
    {
        return app(Asset::class);
    }

    public function query()
    {
        return app(QueryBuilder::class);
    }

    public function save(Asset $asset)
    {
        $asset->writeMeta($asset->generateMeta());
    }
}
