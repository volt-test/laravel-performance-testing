<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Symfony\Component\Console\Style\SymfonyStyle;
use VoltTest\Laravel\Services\RouteDiscoverer;
use VoltTest\Laravel\Services\StepCodeGenerator;
use VoltTest\Laravel\Services\StubRenderer;
use VoltTest\Laravel\Services\VoltTestCreator;

class MakeVoltTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'volttest:make {name : The name of the test}
                            {--routes : Include route discovery for test scaffold}
                            {--filter= : Filter routes by URI pattern (e.g., api/*)}
                            {--method= : Filter routes by HTTP method (GET, POST, etc.)}
                            {--auth : Only include routes with auth middleware}
                            {--select : Interactively select routes to include}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new VoltTest performance test, optionally with route discovery';

    /**
     * The router instance.
     *
     * @var Router
     */
    protected Router $router;

    /**
     * Create a new command instance.
     *
     * @param Router $router
     * @return void
     */
    public function __construct(Router $router)
    {
        parent::__construct();
        $this->router = $router;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $name = $this->argument('name');

        if (! is_string($name) || empty($name)) {
            $this->error('The test name must be a non-empty string.');

            return;
        }

        $io = new SymfonyStyle($this->input, $this->output);

        $creator = new VoltTestCreator(
            new RouteDiscoverer($this->router, $io),
            new StubRenderer(new StepCodeGenerator()),
            $io
        );

        $result = $creator->create($name, [
            'routes' => $this->option('routes'),
            'filter' => $this->option('filter'),
            'method' => $this->option('method'),
            'auth' => $this->option('auth'),
            'select' => $this->option('select'),
        ]);

        $this->info($result);
    }
}
