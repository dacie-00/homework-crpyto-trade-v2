<?php
declare(strict_types=1);

namespace App;

use App\Crypto\Crypto;
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
        ]);
        return $this->helper->ask($this->input, $this->output, $question);
    }

    /**
     * @param Crypto[] $currencies
     */
    public function crypto(array $currencies): string
    {
        $names = [];
        foreach ($currencies as $currency) {
            $names[] = $currency->name();
        }
        $question = new ChoiceQuestion("Select the currency", $names);
        return $this->helper->ask($this->input, $this->output, $question);
    }

    public function quantity(int $min = 1, int $max = 9999): int
    {
        $question = (new Question("Enter the quantity - "))
            ->setValidator(function ($value) use ($min, $max): string {
                if (!is_numeric($value)) {
                    throw new RuntimeException("Quantity must be a number");
                }
                if ($value < $min) {
                    throw new RuntimeException("Quantity must be greater than or equal to $min");
                }
                if ($value > $max) {
                    throw new RuntimeException("Quantity must be less than or equal to $max");
                }
                return $value;
            });
        return (int)($this->helper->ask($this->input, $this->output, $question));
    }
}