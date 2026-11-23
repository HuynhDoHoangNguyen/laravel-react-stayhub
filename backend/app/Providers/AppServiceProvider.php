<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ValidatorInterface::class, static fn () => Validation::createValidatorBuilder()
            ->enableAttributeMapping()->getValidator());
        $this->app->singleton(ObjectMapperInterface::class, static fn () => new ObjectMapper(
            propertyAccessor: PropertyAccess::createPropertyAccessor(),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
