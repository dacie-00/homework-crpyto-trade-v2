<?php
declare(strict_types=1);

namespace App;

class RedirectResponse
{
    private string $template;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function url(): string
    {
        return $this->url;
    }
}