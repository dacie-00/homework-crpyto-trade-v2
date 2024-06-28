<?php
declare(strict_types=1);

use App\Controllers\CurrencyController;
use App\Controllers\NotFoundController;
use App\Controllers\TransactionController;
use App\Controllers\WalletController;
use App\Models\User;
use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\Wallet\WalletRepository;
use App\Services\DatabaseInitializationService;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\BaseCurrencyProvider;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$loader = new FilesystemLoader("app/Templates");
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

$cryptoApi = new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]);
//$cryptoApi = new CoinGeckoApiCurrencyRepository($_ENV["COIN_GECKO_API_KEY"]);

$consoleInput = new ArrayInput([]);
$consoleOutput = new ConsoleOutput();

$provider = new ConfigurableProvider();
$baseProvider = new BaseCurrencyProvider($provider, "EUR");
$currencyConverter = new CurrencyConverter($baseProvider);

$transactionRepository = new TransactionRepository($connection);
$walletRepository = new WalletRepository($connection);

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

// Temporary user autologin
$user = new User("foobar", "3858f62230ac3c915f300c664312c63f", "foobar");
$walletInfo = $walletRepository->getWalletByUserId($user->id());

$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', [CurrencyController::class, "index"]);
    $r->addRoute('GET', '/404', [NotFoundController::class, "index"]);
    $r->addRoute('GET', '/currencies', [CurrencyController::class, "index"]);
    $r->addRoute('GET', '/currencies/{ticker}', [CurrencyController::class, "show"]);
    $r->addRoute('GET', '/transactions', [TransactionController::class, "index"]);
    $r->addRoute('GET', '/wallets/{id}', [WalletController::class, "show"]);
    $r->addRoute('POST', '/wallets/{id}/transfer', [WalletController::class, "transfer"]);
});

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        header("Location: /404");
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        echo "405";
        break;
    case FastRoute\Dispatcher::FOUND:
        $handle = $routeInfo[1];
        $vars = $routeInfo[2];
        [$class, $method] = $handle;
        [$template, $data] = (new $class)->$method(...array_values($vars));
        echo $twig->render($template, $data);
        break;
}