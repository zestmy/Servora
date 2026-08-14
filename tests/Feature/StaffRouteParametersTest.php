<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Staff-app controllers must not take route parameters as arguments.
 *
 * THE BUG THIS EXISTS FOR shipped to production and could not have been caught
 * by any ordinary test. The staff routes are mounted on
 * {companySlug}.servora.com.my, so every one of them has TWO route parameters
 * and `companySlug` comes first — and Laravel's ControllerDispatcher spreads
 * them POSITIONALLY (`...array_values($parameters)`). A controller written as
 * `show(int $id)` therefore receives the SLUG as argument one:
 *
 *     StaffPhotoController::show(): Argument #1 ($employee) must be of type
 *     int, string given
 *
 * Locally and in this suite APP_DOMAIN is unset, so the same routes mount on a
 * plain path with no companySlug, the single parameter lands in the right
 * position, and everything passes. The environment that would catch it is the
 * one nobody runs tests in.
 *
 * So this checks the SHAPE instead of the behaviour: a staff-app action takes
 * Request and injectable services, and reads its parameters by name. That rule
 * holds with or without a domain, which is exactly the property the original
 * code lacked.
 */
class StaffRouteParametersTest extends TestCase
{
    /**
     * Type hints that are resolved by the container rather than positionally,
     * so their order in the signature does not matter.
     */
    private function isInjectable(?\ReflectionType $type): bool
    {
        if (! $type instanceof \ReflectionNamedType) {
            return false;
        }

        return ! $type->isBuiltin();
    }

    public function test_no_staff_controller_takes_a_route_parameter_positionally(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? '';

            // The staff apps, which are the ones mounted on a subdomain.
            if (! str_starts_with($name, 'clock.staff.') && ! str_starts_with($name, 'labels.staff.')) {
                continue;
            }

            $action = $route->getAction('uses');

            // Livewire components are not affected: they receive parameters by
            // name through mount(), not through the controller dispatcher.
            if (! is_string($action) || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action);

            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }

            foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
                if ($this->isInjectable($parameter->getType())) {
                    continue;
                }

                $offenders[] = $name . ' → ' . class_basename($class) . '::' . $method
                    . '($' . $parameter->getName() . ')';
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['These staff-app actions take a route parameter as an argument.'],
            ['On the subdomain, argument one is the company SLUG, not the id —'],
            ['read it with $request->route(\'name\') instead:', ''],
            $offenders,
        )));
    }
}
