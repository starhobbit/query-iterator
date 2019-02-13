<?php

namespace Photonite\QueryIterator\Providers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\ServiceProvider;
use Photonite\QueryIterator\QueryIterator;

class QueryIteratorServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot()
    {
        Builder::macro("toIterator", function ($builder, $chunkSize = null) {
            return new QueryIterator($builder, $chunkSize);
        });

        $this->publishes([
            __DIR__ . '/../../resources/config/queryiterator.php' => config_path('queryiterator.php'),
        ]);
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../resources/config/queryiterator.php', 'queryiterator'
        );
    }
}
