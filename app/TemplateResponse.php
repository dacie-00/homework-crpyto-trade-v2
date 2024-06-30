<?php
declare(strict_types=1);

namespace App;

class TemplateResponse
{
    private string $template;
    private array $data;

    public function __construct(string $template, array $data = [])
    {
        $this->template = $template;
        $this->data = $data;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function template(): string
    {
        return $this->template;
    }

}