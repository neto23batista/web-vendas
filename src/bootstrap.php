<?php

if (!defined('FARMAVIDA_ROOT')) {
    define('FARMAVIDA_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/Core/Autoloader.php';

\FarmaVida\Core\Autoloader::register(__DIR__);

use FarmaVida\Application\Auth\AuthService;
use FarmaVida\Application\Store\CatalogService;
use FarmaVida\Core\Config\AppConfig;
use FarmaVida\Core\Container;
use FarmaVida\Core\Database\Database;
use FarmaVida\Core\Security\CsrfManager;
use FarmaVida\Core\Security\FlashMessages;
use FarmaVida\Core\Security\SessionManager;
use FarmaVida\Core\View\ViewRenderer;
use FarmaVida\Infrastructure\Repository\ProductCatalogRepository;
use FarmaVida\Infrastructure\Repository\UserRepository;
use FarmaVida\Infrastructure\Services\MailerGateway;
use FarmaVida\Infrastructure\Services\ProductImageResolver;
use FarmaVida\Presentation\Web\Controllers\Auth\LoginController;
use FarmaVida\Presentation\Web\Controllers\Auth\LogoutController;
use FarmaVida\Presentation\Web\Controllers\Auth\RegisterController;
use FarmaVida\Presentation\Web\Controllers\Store\HomeController;

$container = new Container();

$container->set(AppConfig::class, fn() => AppConfig::fromEnvironment(FARMAVIDA_ROOT));
$container->set(SessionManager::class, fn() => new SessionManager());
$container->set(FlashMessages::class, fn(Container $c) => new FlashMessages($c->get(SessionManager::class)));
$container->set(CsrfManager::class, fn(Container $c) => new CsrfManager($c->get(SessionManager::class)));
$container->set(Database::class, fn(Container $c) => new Database($c->get(AppConfig::class)));
$container->set(ViewRenderer::class, fn(Container $c) => new ViewRenderer($c->get(AppConfig::class)->viewPath(), $c->get(AppConfig::class)));
$container->set(UserRepository::class, fn(Container $c) => new UserRepository($c->get(Database::class)));
$container->set(ProductImageResolver::class, fn(Container $c) => new ProductImageResolver($c->get(AppConfig::class)));
$container->set(ProductCatalogRepository::class, fn(Container $c) => new ProductCatalogRepository($c->get(Database::class)));
$container->set(MailerGateway::class, fn() => new MailerGateway());
$container->set(AuthService::class, fn(Container $c) => new AuthService(
    $c->get(UserRepository::class),
    $c->get(SessionManager::class),
    $c->get(FlashMessages::class),
    $c->get(MailerGateway::class)
));
$container->set(CatalogService::class, fn(Container $c) => new CatalogService(
    $c->get(ProductCatalogRepository::class),
    $c->get(ProductImageResolver::class),
    $c->get(SessionManager::class),
    $c->get(FlashMessages::class),
    $c->get(CsrfManager::class)
));
$container->set(LoginController::class, fn(Container $c) => new LoginController(
    $c->get(AuthService::class),
    $c->get(ViewRenderer::class),
    $c->get(CsrfManager::class),
    $c->get(FlashMessages::class)
));
$container->set(RegisterController::class, fn(Container $c) => new RegisterController(
    $c->get(AuthService::class),
    $c->get(ViewRenderer::class),
    $c->get(CsrfManager::class),
    $c->get(FlashMessages::class)
));
$container->set(LogoutController::class, fn(Container $c) => new LogoutController($c->get(AuthService::class)));
$container->set(HomeController::class, fn(Container $c) => new HomeController(
    $c->get(CatalogService::class),
    $c->get(ViewRenderer::class)
));

return $container;
