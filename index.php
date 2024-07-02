<?php
declare(strict_types=1);

require_once "vendor/autoload.php";

use App\RedirectResponse;
use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\Transaction\DoctrineDbalTransactionRepository;
use App\Repositories\Transaction\TransactionRepositoryInterface;
use App\Repositories\User\DoctrineDbalUserRepository;
use App\Repositories\User\UserRepositoryInterface;
use App\Repositories\Wallet\DoctrineDbalWalletRepository;
use App\Repositories\Wallet\WalletRepositoryInterface;
use App\Services\DatabaseInitializationService;
use App\TemplateResponse;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use function DI\create;
use function DI\get;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$loader = new FilesystemLoader("templates");
$twig = new Environment($loader);

$connectionParams = [
    "driver" => "pdo_sqlite",
    "path" => "storage/database.sqlite",
];

$builder = new ContainerBuilder();
$builder->addDefinitions(
    [
        Connection::class =>
            DriverManager::getConnection($connectionParams),
        CurrencyRepositoryInterface::class =>
            new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]),
        TransactionRepositoryInterface::class =>
            create(DoctrineDbalTransactionRepository::class)->constructor(get(Connection::class)),
        UserRepositoryInterface::class =>
            create(DoctrineDbalUserRepository::class)->constructor(get(Connection::class)),
        WalletRepositoryInterface::class =>
            create(DoctrineDbalWalletRepository::class)->constructor(get(Connection::class)),
    ]
);
$container = $builder->build();


$userRepository = $container->get(UserRepositoryInterface::class);

$databaseInitializer = $container->get(DatabaseInitializationService::class);
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
        $response = $container->get($class)->$method(...array_values($vars));
        if ($response instanceof TemplateResponse) {
            echo $twig->render($response->template() . ".html.twig", $response->data());
        } elseif ($response instanceof RedirectResponse) {
            header("Location: {$response->url()}");
        }
        break;
}