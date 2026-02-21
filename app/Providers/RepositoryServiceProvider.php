<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\User\UserRepositoryInterface;
use App\Contracts\Category\CategoryRepositoryInterface;
use App\Contracts\ShopifyLogs\ShopifyLogsRepositoryInterface;
use App\Repositories\User\EloquentUserRepository;
use App\Repositories\Category\EloquentCategoryRepository;
use App\Repositories\ShopifyLogs\EloquentShopifyLogsRepository;
use App\Repositories\Shopify\EloquentShopifyProductsRepository;
use App\Contracts\Shopify\ShopifyProductsRepositoryInterface;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            CategoryRepositoryInterface::class,
            EloquentCategoryRepository::class
        );

        $this->app->bind(
            ShopifyLogsRepositoryInterface::class,
            EloquentShopifyLogsRepository::class
        );

        $this->app->bind(
            ShopifyProductsRepositoryInterface::class,
            EloquentShopifyProductsRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
