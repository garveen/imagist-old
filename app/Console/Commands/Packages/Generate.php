<?php

namespace App\Console\Commands\Packages;

use Illuminate\Console\Command;

class Generate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate packages.json';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return app('App\Http\Controllers\ProxyController')->generate();
    }
}
