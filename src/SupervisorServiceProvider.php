<?php

namespace Crm\Supervisor;

use Crm\Supervisor\Console\MakeSupervisorCommand;
use Illuminate\Support\ServiceProvider;

class SupervisorServiceProvider extends ServiceProvider
{

    protected $commands = [
        MakeSupervisorCommand::class,
    ];

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands($this->commands);
    }
}
