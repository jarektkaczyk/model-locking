<?php

namespace Sofa\ModelLocking;

use Carbon\Carbon;

/**
 * @package sofa/model-locking
 * @author Jarek Tkaczyk <jarek@softonsofa.com>
 * @link https://github.com/jarektkaczyk/model-locking
 */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        $stubs = realpath(__DIR__.'/../published');
        $timestamp = Carbon::now()->format('Y_m_d_His');

        $this->publishes([
            $stubs.'/config.stub' => config_path('model_locking.php'),
            $stubs.'/migration.stub' => database_path("migrations/{$timestamp}_create_model_locks_table.php"),
        ]);
    }

    public function register()
    {
        $this->commands(FlushExpiredLocks::class);
    }
}
