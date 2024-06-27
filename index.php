<?php
declare(strict_types=1);

use App\Ask;
use App\Controllers\CurrencyController;
use App\Controllers\TransactionController;
use App\Controllers\WalletController;
use App\Display;
use App\Exceptions\NoMoneyException;
use App\Models\User;
use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\Wallet\WalletRepository;
use App\Services\BuyService;
use App\Services\DatabaseInitializationService;
use App\Services\SellService;
use App\Services\WalletService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
$ask = new Ask($consoleInput, $consoleOutput);

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
        // ... 404 Not Found
        echo "404";
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

die;



$display = new Display($consoleOutput);
echo "Welcome, {$user->username()}!\n";
while (true) {
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            try {
                $moneyInWallet = $walletService->getMoney($walletInfo, "EUR")->getAmount();
            } catch (NoMoneyException $e) {
                echo "You don't have any money left to spend on cryptocurrencies!\n";
                break;
            }
            $ticker = $ask->ticker();
            $amount = $ask->amount($moneyInWallet);
            $extendedCurrencies = $cryptoApi->search([$ticker]);
            if (empty($extendedCurrencies)) {
                echo "Could not find any currency with ticker {$ticker}\n";
                break;
            }
            $extendedCurrency = $extendedCurrencies[0];

            $provider->setExchangeRate(
                "EUR",
                $extendedCurrency->ticker(),
                $extendedCurrency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");

            $transaction = (new BuyService($connection, $walletService, $transactionRepository, $currencyConverter))
                ->execute($walletInfo, $amount, $extendedCurrency);

            $sentMoney = $transaction->sentMoney();
            $receivedMoney = $transaction->receivedMoney();
            echo "{$receivedMoney->getAmount()} {$receivedMoney->getCurrency()->getCurrencyCode()} bought for " .
                "{$sentMoney->getAmount()} {$sentMoney->getCurrency()->getCurrencyCode()}\n";
            break;
        case Ask::ACTION_SELL:
            $ownedCurrencies = [];
            foreach ($walletService->getWalletById($walletInfo->id())->contents() as $money) {
                if ($money->getCurrency()->getCurrencyCode() === "EUR") {
                    continue;
                }
                $ownedCurrencies[] = $money->getCurrency();
            }
            if (empty($ownedCurrencies)) {
                echo "You don't own any cryptocurrencies!\n";
                break;
            }
            $currency = $ask->crypto($ownedCurrencies);
            $amount = $ask->amount($money->getAmount());

            $extendedCurrencies = $cryptoApi->search([$currency->getCurrencyCode()]);
            if (empty($extendedCurrencies)) {
                echo "Failed to fetch currency price\n";
                break;
            }
            $extendedCurrency = $extendedCurrencies[0];

            $provider->setExchangeRate(
                "EUR",
                $extendedCurrency->ticker(),
                $extendedCurrency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");

            $transaction =
                (new SellService($connection, $walletService, $transactionRepository, $currencyConverter))
                    ->execute($walletInfo, $amount, $extendedCurrency);

            $sentMoney = $transaction->sentMoney();
            $receivedMoney = $transaction->receivedMoney();
            echo "{$sentMoney->getAmount()} {$sentMoney->getCurrency()->getCurrencyCode()} sold for " .
                "{$receivedMoney->getAmount()} {$receivedMoney->getCurrency()->getCurrencyCode()}\n";

            break;
        case Ask::ACTION_WALLET:
            $wallet = $walletRepository->getWalletById($walletInfo->id());

            $currencyCodes = array_map(fn($money) => $money->getCurrency()->getCurrencyCode(), $wallet->contents());
            $currencyDate = $cryptoApi->search($currencyCodes);
            $marketCurrencies = [];
            foreach ($currencyDate as $currency) {
                $marketCurrencies[$currency->definition()->getCurrencyCode()] = $currency;
            }

            $percentages = [];
            foreach ($wallet->contents() as $index => $money) {
                if ($money->getCurrency()->getCurrencyCode() === "EUR") {
                    $percentages[] = "Not available";
                    continue;
                }
                $average = $transactionRepository->getAveragePrice($user, $money->getCurrency(), $money->getAmount());
                $marketRate = BigDecimal::one()->dividedBy($marketCurrencies[$money->getCurrency()->getCurrencyCode()]->exchangeRate(), null, RoundingMode::DOWN);
                $percentages[] =
                    Bigdecimal::of(100)
                        ->multipliedBy(
                            $marketRate->dividedBy($average, 9, RoundingMode::DOWN)
                                ->minus(BigDecimal::one()))->toFloat();
            }
            $display->wallet($wallet, $percentages);
            break;
        case Ask::ACTION_HISTORY:
            $display->transactions($transactionRepository->getByUser($user));
            break;
        case Ask::ACTION_LIST:
            $display->currencies($cryptoApi->getTop());
            break;
        case Ask::ACTION_SEARCH:
            $query = $ask->query();
            $codes = explode(",", $query);
            $codes = array_map(static fn($value) => trim($value), $codes);
            $foundCurrencies = $cryptoApi->search($codes);
            if (empty($foundCurrencies)) {
                echo "No currency found!\n";
                break;
            }

            $display->currencies($foundCurrencies);

            break;
        case Ask::ACTION_EXIT:
            exit;
    }
}

