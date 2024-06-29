<?php
declare(strict_types=1);

use App\Controllers\CurrencyController;
use App\Controllers\ErrorController;
use App\Controllers\TransactionController;
use App\Controllers\WalletController;
use App\Repositories\UserRepository;
use App\Services\DatabaseInitializationService;
use Doctrine\DBAL\DriverManager;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$loader = new FilesystemLoader("templates");
$twig = new Environment($loader);

$connectionParams = [
    "driver" => "pdo_sqlite",
    "path" => "storage/database.sqlite",
];

$connection = DriverManager::getConnection($connectionParams);

$userRepository = new UserRepository($connection);

$databaseInitializer = new DatabaseInitializationService($connection);
$databaseInitializer->initializeUsersTable();
$databaseInitializer->initializeTransactionsTable();
$databaseInitializer->initializeWalletsTable($userRepository);

//$users = $userRepository->getAll();
//$user = null;
//while (true) {
//    [$username, $password] = $ask->login();
//    if ($user = (new UserValidationService($userRepository))->login($username, $password)) {
//        break;
//    }
//    echo "Incorrect username or password!\n";
//}
//
//$walletInfo = $walletService->getUserWallet($user);

$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) {
    $routes = include __DIR__ . "/routes.php";
    foreach ($routes as $route) {
        $r->addRoute($route[0], $route[1], $route[2]);
    }
});

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        header("Location: /404");
        break;
    case FastRoute\Dispatcher::FOUND:
        $handle = $routeInfo[1];
        $vars = $routeInfo[2];
        [$class, $method] = $handle;
        [$template, $data] = (new $class)->$method(...array_values($vars));
        echo $twig->render($template, $data);
        break;
}