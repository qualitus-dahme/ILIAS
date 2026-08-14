<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS;

use ILIAS\ApiGateway\Activity\ActivityNamespaceFactory;
use ILIAS\ApiGateway\Activity\ActivityRouteFactory;
use ILIAS\ApiGateway\Application\Factory\HttpConfigFactory;
use ILIAS\ApiGateway\Application\Factory\HttpServiceFactory;
use ILIAS\ApiGateway\Application\Factory\RoutesRegistryFactory;
use ILIAS\ApiGateway\Application\Factory\WebAppFactory;
use ILIAS\ApiGateway\Auth;
use ILIAS\ApiGateway\Auth\Domain\Repository\UserRepository;
use ILIAS\ApiGateway\Auth\Domain\Service\Authentication;
use ILIAS\ApiGateway\Auth\Infrastructure\DatabaseRefreshTokenRepository;
use ILIAS\ApiGateway\Auth\Infrastructure\DatabaseUserRepository;
use ILIAS\ApiGateway\Configuration;
use ILIAS\ApiGateway\Logging\WebserviceLoggerFactory;
use ILIAS\ApiGateway\Middleware\MiddlewareRepository;
use ILIAS\ApiGateway\RestAppEntryPoint;
use ILIAS\ApiGateway\Routing\Route;
use ILIAS\ApiGateway\Routing\RouteRepository;
use ILIAS\ApiGateway\Webservice\WebserviceFactory;
use Psr\Http\Server\MiddlewareInterface;

class ApiGateway implements Component\Component
{
    #[\Override]
    public function init(
        array | \ArrayAccess &$define,
        array | \ArrayAccess &$implement,
        array | \ArrayAccess &$use,
        array | \ArrayAccess &$contribute,
        array | \ArrayAccess &$seek,
        array | \ArrayAccess &$provide,
        array | \ArrayAccess &$pull,
        array | \ArrayAccess &$internal,
    ): void {
        $contribute[\ILIAS\Setup\Agent::class] = fn() =>
        new ApiGateway\Setup\ilApiGatewaySetupAgent(
            $pull[\ILIAS\Refinery\Factory::class],
        );

        $contribute[Component\Resource\PublicAsset::class] = fn(): Component\Resource\OfComponent =>
        new Component\Resource\Endpoint($this, "rest/index.php", "rest");

        $contribute[Component\Resource\PublicAsset::class] = fn(): Component\Resource\OfComponent =>
        new Component\Resource\OfComponent($this, "rest/.htaccess", "rest");

        /**
         * Main declarations to be consumed by other components
         */
        $define[] = ApiGateway\Routing\Route::class;

        ///////////////////////////////////////////////////////////////////////////////////
        //////////////////////////////// Application Layer ////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////

        $internal[RoutesRegistryFactory::class] = static fn(): RoutesRegistryFactory => new RoutesRegistryFactory(
            new RouteRepository(
                $seek[ApiGateway\Routing\Route::class],
            ),
            $use[Component\Activities\Repository::class],
            new ActivityRouteFactory(
                new ActivityNamespaceFactory(),
                $use[\ILIAS\UI\Factory::class]->input(),
            ),
        );

        $internal[WebAppFactory::class] = static fn(): WebAppFactory =>

        new WebAppFactory(
            $internal[HttpConfigFactory::class],
            new HttpServiceFactory(),
            new WebserviceFactory(),
            $internal[RoutesRegistryFactory::class],
            new MiddlewareRepository(
                $seek[MiddlewareInterface::class],
            ),
            new HTTP\Response\ResponseFactoryImpl(), // $use[HTTP\Response\ResponseFactory::class],
            new WebserviceLoggerFactory(),
        );

        ///////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////// Service Contributions ///////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////

        /**
         * Middlewares
         */
        $contribute[MiddlewareInterface::class] = static fn(): MiddlewareInterface =>
        new ApiGateway\Middleware\AuthenticationMiddleware(
            $internal[Authentication::class],
        );

        /**
         * API Application Endpoints
         */

        ## /ping
        $contribute[Route::class] = static fn(): Route => new ApiGateway\Routes\PingRoute();

        ## /activities
        $contribute[Route::class] = static fn(): Route => new ApiGateway\Examples\GetActivityListApiAction(
            $use[Component\Activities\Repository::class],
            new ActivityRouteFactory(
                new ActivityNamespaceFactory(),
                $use[\ILIAS\UI\Factory::class]->input(),
            ),
            'rest',
        );

        ## /auth/token
        $contribute[Route::class] = static fn(): Route =>
        new ApiGateway\Routes\Auth\IssueTokenRoute(
            $internal[Authentication::class],
            $internal[UserRepository::class],
        );

        ## /auth/refresh
        $contribute[Route::class] = static fn(): Route =>
        new ApiGateway\Routes\Auth\RefreshTokenRoute(
            $internal[Authentication::class],
        );

        ///////////////////////////////////////////////////////////////////////////////////
        ////////////////////////// Main Application Entry Points //////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////

        $contribute[Component\EntryPoint::class] = static fn(): Component\EntryPoint =>
        new RestAppEntryPoint(
            $internal[WebAppFactory::class],
        );

        ///////////////////////////////////////////////////////////////////////////////////
        //////////////////////////////// Shared internally ////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////

        /**
         * @var ApiGateway\Application\Factory\HttpConfigFactory
         *
         * workaround @todo: replace with DI then replace usage with config objects
         */
        $internal[HttpConfigFactory::class] = static fn(): HttpConfigFactory =>
        new HttpConfigFactory(
            $internal[Configuration\Domain\Configuration::class],
        );

        ///////////////////////////////////////////////////////////////////////////////////
        /////////////////////////////////// SUB-MODULES ///////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////

        // MODULE: Auth

        $internal[UserRepository::class] = static fn(): UserRepository => new DatabaseUserRepository();
        $internal[Authentication::class] = static fn(): Authentication =>
        new Auth\Infrastructure\AuthService(
            new Auth\Infrastructure\JwtService(
                $internal[HttpConfigFactory::class], // workaround @todo: replace with DI
            ),
            $internal[UserRepository::class],
            new DatabaseRefreshTokenRepository(),
            $internal[HttpConfigFactory::class], // workaround @todo: replace with DI
        );

        // MODULE: Configuration

        $internal[Configuration\Domain\Configuration::class] = static fn(): Configuration\Domain\Configuration =>
        new Configuration\Infrastructure\ConfigurationService(
            new Configuration\Infrastructure\Repository\AdminSettings(),
        );
    }
}
