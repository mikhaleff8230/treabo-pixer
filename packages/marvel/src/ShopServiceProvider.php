<?php

namespace Marvel;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Marvel\Http\Middleware\EnsureEmailIsVerified;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use Marvel\Enums\CouponType;
use Marvel\Enums\ShippingType;
use Marvel\Enums\Permission;
use Marvel\Providers\GraphQLServiceProvider;
use Marvel\Providers\RestApiServiceProvider;
use Marvel\Providers\EventServiceProvider;
use Marvel\Console\InstallCommand;
use Illuminate\Support\Facades\App;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Illuminate\Support\Facades\Gate;
use Marvel\Ai\Ai;
use Marvel\Console\AdminCreateCommand;
use Marvel\Console\CopyFilesCommand;
use Marvel\Console\GenerateBitPayConfig;
use Marvel\Console\ImportDemoData;
use Marvel\Console\SettingsDataImporter;
use Marvel\Console\FixRolesCommand;
use Marvel\Console\Commands\SeedAttributeSystemCommand;
use Marvel\Console\Commands\TestProductCreationCommand;
use Marvel\Console\Commands\TestAttributeCreationCommand;
use Marvel\Database\Models\Settings;
use Marvel\Enums\EventType;
use Marvel\Enums\ManufacturerType;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\ProductType;
use Marvel\Enums\RefundStatus;
use Marvel\Enums\WithdrawStatus;
use Marvel\Enums\PaymentGatewayType;
use Marvel\Enums\PaymentStatus;
use Marvel\Enums\ProductStatus;
use Marvel\Payments\Payment;
use Marvel\Enums\StoreNoticePriority;
use Marvel\Enums\StoreNoticeType;

class ShopServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected $serviceProviders = [
        GraphQLServiceProvider::class,
        RestApiServiceProvider::class,
        EventServiceProvider::class,
        WhereConditionsServiceProvider::class,
        // Maatwebsite\Excel\ExcelServiceProvider::class,

    ];

    /**
     * @var array
     */
    protected $enums = [
        CouponType::class,
        Permission::class,
        StoreNoticeType::class,
        StoreNoticePriority::class,
        ShippingType::class,
        ProductType::class,
        WithdrawStatus::class,
        RefundStatus::class,
        PaymentGatewayType::class,
        ManufacturerType::class,
        OrderStatus::class,
        PaymentStatus::class,
        ProductStatus::class,
        EventType::class,
    ];

    protected $commandList = [
        InstallCommand::class,
        AdminCreateCommand::class,
        ImportDemoData::class,
        CopyFilesCommand::class,
        GenerateBitPayConfig::class,
        SettingsDataImporter::class,
        FixRolesCommand::class,
        \Marvel\Console\Commands\UpdateShipmentStatuses::class,
        \Marvel\Console\Commands\TestShipmentCreation::class,
        \Marvel\Console\Commands\ElasticsearchSetup::class,
        \Marvel\Console\Commands\ElasticsearchStatus::class,
        \Marvel\Console\Commands\ElasticsearchIndexProducts::class,
        \Marvel\Console\Commands\ElasticsearchIndexPlaces::class,
        \Marvel\Console\Commands\ElasticsearchVerify::class,
        \Marvel\Console\Commands\ElasticsearchDiagnose::class,
        \Marvel\Console\Commands\TestEmailCommand::class,
        \Marvel\Console\Commands\TestAllEmailsCommand::class,
        \Marvel\Console\Commands\SeedAttributeSystemCommand::class,
        \Marvel\Console\Commands\TestProductCreationCommand::class,
        \Marvel\Console\Commands\TestAttributeCreationCommand::class,
        \Marvel\Console\Commands\TestProductGroupSkuCommand::class,
        \Marvel\Console\Commands\MigrateProductVariationsCommand::class,
        \Marvel\Console\Commands\TestPlaceSlugCommand::class,
    ];

    /**
     * @var string[]
     */
    protected $routeMiddleware = [
        'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
        'email.verified' => EnsureEmailIsVerified::class,
    ];


    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(TypeRegistry $typeRegistry): void
    {
        $this->loadServiceProviders();
        $this->loadMiddleware();
        $this->bootConsole();
        $this->registerEnum($typeRegistry);
        $this->givePermissionToSuperAdmin();
        $this->loadMigrations();
        $this->loadHelpers();
        $this->registerElasticsearchObservers();
    }

    public function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // $this->loadFactoriesFrom(__DIR__ . '/../database/factories');
    }

    /**
     * If the helper file exists, require it.
     */
    public function loadHelpers()
    {
        if (File::exists(__DIR__ . '/Helpers/helpers.php')) {
            require(__DIR__ . '/Helpers/helpers.php');
        }
    }

    /**
     * Load Service Providers
     *
     * @return void
     */
    public function loadServiceProviders(): void
    {
        foreach ($this->serviceProviders as $provider) {
            App::register($provider);
        }
    }

    public function givePermissionToSuperAdmin()
    {
        Gate::before(function ($user, $ability) {
            return $user->hasPermissionTo(Permission::SUPER_ADMIN) ? true : null;
        });
    }

    public function registerEnum($typeRegistry)
    {
        foreach ($this->enums as $enum) {
            $typeRegistry->register(
                new LaravelEnumType($enum)
            );
        }
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function bootConsole()
    {
        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {

        $this->mergeConfigFrom(__DIR__ . '/../config/shop.php', 'shop');
        $this->mergeConfigFrom(__DIR__ . '/../config/elasticsearch.php', 'elasticsearch');

        config([
            'auth' => File::getRequire(__DIR__ . '/../config/auth.php'),
            'cors' => File::getRequire(__DIR__ . '/../config/cors.php'),
            'graphql-playground' => File::getRequire(__DIR__ . '/../config/graphql-playground.php'),
            'laravel-omnipay' => File::getRequire(__DIR__ . '/../config/laravel-omnipay.php'),
            'media-library' => File::getRequire(__DIR__ . '/../config/media-library.php'),
            'permission' => File::getRequire(__DIR__ . '/../config/permission.php'),
            'services' => File::getRequire(__DIR__ . '/../config/services.php'),
            'scout' => File::getRequire(__DIR__ . '/../config/scout.php'),
            'sluggable' => File::getRequire(__DIR__ . '/../config/sluggable.php'),
            'constants' => File::getRequire(__DIR__ . '/../config/constants.php'),
            'newsletter' => File::getRequire(__DIR__ . '/../config/newsletter.php'),
            'paystack' => File::getRequire(__DIR__ . '/../config/paystack.php'),
            'paymongo' => File::getRequire(__DIR__ . '/../config/paymongo.php'),
            'graphiql' => File::getRequire(__DIR__ . '/../config/graphiql.php'),
            'sslcommerz' => File::getRequire(__DIR__ . '/../config/sslcommerz.php'),
            'sslcommerz' => File::getRequire(__DIR__ . '/../config/sslcommerz.php'),
        ]);

        // Register the service the package provides.
        $this->app->singleton('shop', function () {
            return new Shop();
        });

        $this->app->singleton('payment', function ($app) {
            $active_payment_gateway = '';
            $settings = Settings::first();

            if (!empty(request()) && request()->has('payment_gateway') && !in_array(request()['payment_gateway'], [PaymentGatewayType::CASH_ON_DELIVERY, PaymentGatewayType::CASH])) {
                $active_payment_gateway = ucfirst(strtolower(request()['payment_gateway']));
            } else {
                // Используем YooKassa как дефолтный платежный шлюз
                $active_payment_gateway = $settings->options['defaultPaymentGateway'] ?? 'YooKassa';
            }
            
            // Нормализуем Tinkoff на YooKassa для обратной совместимости
            if (strtolower($active_payment_gateway) === 'tinkoff') {
                $active_payment_gateway = 'YooKassa';
            }
            
            try {
                $gateway = 'Marvel\\Payments\\' . ucfirst(strtolower($active_payment_gateway));
                return new Payment($app->make($gateway));
            } catch (\Throwable $th) {
                // Fallback на YooKassa
                $gateway = 'Marvel\\Payments\\' . 'YooKassa';
                return new Payment($app->make($gateway));
            }

        });

        $this->app->singleton('ai', function ($app) {
            $active_ai = '';
            $settings = Settings::first();

            if (!empty(request()) && request()->has('artificial_intelligence')) {
                $active_ai = ucfirst(strtolower(request()['artificial_intelligence']));
            } else {
                $active_ai = $settings->options['defaultAi'];
            }

            $ai = 'Marvel\\Ai\\' . ucfirst($active_ai);
            return new Ai($app->make($ai));
        });

        // Регистрация PaymentGatewayManager
        $this->app->singleton('payment.gateway.manager', function ($app) {
            return new \App\Services\PaymentGatewayManager();
        });

        // Регистрация ElasticsearchService
        $this->app->singleton(\Marvel\Services\ElasticsearchService::class);
        
        // Регистрация EmailService
        $this->app->singleton(\Marvel\Services\EmailService::class);
    }
    
    /**
     * Register Elasticsearch Observers
     */
    protected function registerElasticsearchObservers(): void
    {
        if (!config('elasticsearch.enabled', false)) {
            return;
        }

        \Marvel\Database\Models\Product::observe(\Marvel\Observers\ProductElasticsearchObserver::class);
        \Marvel\Database\Models\Place::observe(\Marvel\Observers\PlaceElasticsearchObserver::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['shop'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config//shop.php' => config_path('shop.php'),
        ], 'config');

        $this->commands($this->commandList);
    }


    /**
     * Load Middleware from shop
     */
    protected function loadMiddleware(): void
    {
        if (!is_array($this->routeMiddleware) ||  empty($this->routeMiddleware)) {
            return;
        }

        foreach ($this->routeMiddleware as $alias => $middleware) {
            $this->app->router->aliasMiddleware($alias, $middleware);
        }
    }
}
