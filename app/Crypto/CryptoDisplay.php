<?php
declare(strict_types=1);

namespace App\Crypto;

use App\Currency;
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
    public function display(array $currencies): void
    {
        $table = (new Table($this->output))
            ->setHeaderTitle("Cryptocurrencies")
            ->setHeaders([
                "Name",
                "Ticker",
                "Price (EUR)",
            ]);

        foreach ($currencies as $currency) {
            $table->addRow([
                $currency->name(),
                $currency->ticker(),
                $currency->exchangeRate(),
            ]);
        }
        $table->render();
    }

}