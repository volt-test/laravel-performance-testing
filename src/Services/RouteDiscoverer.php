<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Console\Style\SymfonyStyle;

class RouteDiscoverer
{
    public function __construct(
        protected Router $router,
        protected SymfonyStyle $io,
    ) {
    }

    /**
     * Discover routes based on the provided options.
     *
     * @param array $options
     * @return array
     */
    public function discover(array $options): array
    {
        $allRoutes = Route::getRoutes()->getRoutes();
        $filteredRoutes = [];

        foreach ($allRoutes as $route) {
            $uri = $route->uri();
            if (empty($uri)) {
                continue;
            }

            if (! empty($options['method']) && ! in_array(strtoupper($options['method']), $route->methods(), true)) {
                continue;
            }

            if (! empty($options['filter'])) {
                $patterns = array_map('trim', explode(',', $options['filter']));
                if (! collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $uri))) {
                    continue;
                }
            }

            if (! empty($options['auth'])) {
                $middleware = $route->middleware();
                if (! in_array('auth', $middleware, true) && ! in_array('auth:api', $middleware, true)) {
                    continue;
                }
            }

            $middleware = $route->middleware();
            $type = (in_array('api', $middleware, true) || Str::startsWith($uri, 'api/')) ? 'api' : 'web';

            $filteredRoutes[] = [
                'methods' => $route->methods(),
                'uri' => $uri,
                'name' => $route->getName() ?: '',
                'controller' => $route->getActionName(),
                'middleware' => $middleware,
                'type' => $type,
            ];
        }

        if (empty($filteredRoutes)) {
            $this->io->warning('No routes matched the given filters.');

            return [];
        }

        return ! empty($options['select'])
            ? $this->selectRoutes($filteredRoutes)
            : $filteredRoutes;
    }

    /**
     * Interactively select routes from the discovered routes.
     *
     * @param array $routes
     * @return array
     */
    protected function selectRoutes(array $routes): array
    {
        $choices = [];
        foreach ($routes as $index => $route) {
            $label = "[{$route['methods'][0]}] {$route['uri']}";
            if ($route['name']) {
                $label .= " (Name: {$route['name']})";
            }
            $choices[$index] = $label;
        }

        $selected = $this->io->choice(
            'Select routes to include (use arrows + space to select, Enter to confirm)',
            $choices,
            null,
            true
        );

        return collect($selected)
            ->map(function ($label) use ($choices, $routes) {
                $index = array_search($label, $choices, true);

                return $routes[$index];
            })
            ->toArray();
    }
}
