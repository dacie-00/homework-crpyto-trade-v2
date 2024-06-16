<?php
declare(strict_types=1);

namespace App;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Ask
{
    public const ACTION_BUY = "buy";
    public const ACTION_SELL = "sell";
    public const ACTION_WALLET = "view your wallet";
    public const ACTION_HISTORY = "display transaction history";
    public const ACTION_LIST = "list currencies";
    public const ACTION_SEARCH = "search for currency";
    public const ACTION_EXIT = "exit";
    private InputInterface $input;
    private OutputInterface $output;
    private QuestionHelper $helper;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->helper = new QuestionHelper();
    }

    public function mainAction(): string
    {
        $question = new ChoiceQuestion("What do you want to do?", [
            self::ACTION_BUY,
            self::ACTION_SELL,
            self::ACTION_WALLET,
            self::ACTION_HISTORY,
            self::ACTION_LIST,
            self::ACTION_SEARCH,
            self::ACTION_EXIT,
        ]);
        return $this->helper->ask($this->input, $this->output, $question);
    }

    /**
     * @param Currency[] $currencies
     */
    public function crypto(array $currencies): Currency
    {
        $names = [];
        foreach ($currencies as $currency) {
            $names[] = $currency->getCurrencyCode();
        }
        $question = new ChoiceQuestion("Select the currency", $names);
        $ticker = $this->helper->ask($this->input, $this->output, $question);
        foreach ($currencies as $currency) {
            if ($currency->getCurrencyCode() === $ticker) {
                return $currency;
            }
        }
    }

    public function amount(BigDecimal $max): float
    {
        $min = BigDecimal::of(0.00000001);
        $min->toScale(8, RoundingMode::DOWN);
        $max->toScale(8, RoundingMode::DOWN);
        $question = (new Question("Enter the amount of euro you wish to spend ($min-$max) - "))
            ->setValidator(function ($value) use ($min, $max): string {
                if (!is_numeric($value)) {
                    throw new RuntimeException("Quantity must be a number");
                }
                if ($min->isGreaterThan($value)) {
                    throw new RuntimeException("Quantity must be greater than or equal to $min");
                }
                if ($max->isLessThan($value)) {
                    throw new RuntimeException("Quantity must be less than or equal to $max");
                }
                return $value;
            });
        return (float)($this->helper->ask($this->input, $this->output, $question));
    }

    public function query(): string
    {
        $question = (new Question("Enter a ticker to search for (separate multible by comma) - "))
            ->setValidator(function ($ticker): string {
                if (empty($ticker)) {
                    throw new RuntimeException("Ticker cannot be empty");
                }
                return $ticker;
            });
        return (string)($this->helper->ask($this->input, $this->output, $question));
    }

    public function ticker()
    {
        $question = (new Question("Enter the ticker of the currency you wish to purchase - "))
            ->setValidator(function ($ticker): string {
                if (empty($ticker)) {
                    throw new RuntimeException("The ticker cannot be empty");
                }
                if (is_numeric($ticker)) {
                    throw new RuntimeException("The ticker cannot be a numeric value");
                }
                return $ticker;
            });
        return strtoupper((string)($this->helper->ask($this->input, $this->output, $question)));
    }
}