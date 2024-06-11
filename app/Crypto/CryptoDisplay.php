<?php
declare(strict_types=1);

namespace App\Crypto;

use Brick\Money\Currency;
use Brick\Money\ExchangeRateProvider;
use Brick\Math\RoundingMode;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class CryptoDisplay
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param Currency[] $currencies
     */
    public function display(array $currencies, ExchangeRateProvider $provider): void
    {
        $table = (new Table($this->output))
            ->setHeaderTitle("Cryptocurrencies")
            ->setHeaders([
                "Name",
                "Ticker",
                "Price (EUR)",
            ]);

        foreach ($currencies as $currency) {
            if ($currency->getCurrencyCode() === "EUR") {
                continue;
            }
            $table->addRow([
                $currency->getName(),
                $currency->getCurrencyCode(),
                $provider->getExchangeRate($currency->getCurrencyCode(), "EUR")->toScale(8, RoundingMode::DOWN)
            ]);
        }
        $table->render();
    }

}