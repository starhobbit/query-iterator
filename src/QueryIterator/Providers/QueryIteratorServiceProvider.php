<?php

namespace Photonite\QueryIterator\Providers;

use Photonite\QueryIterator\QueryIterator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class QueryIteratorServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot()
    {
        Builder::macro("toIterator", function ($chunkSize = null) {
            return new QueryIterator($this, $chunkSize);
        });

        EloquentBuilder::macro("toIterator", function ($chunkSize = null) {
            return new QueryIterator($this, $chunkSize);
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
