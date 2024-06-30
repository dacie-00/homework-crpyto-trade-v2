<?php
declare(strict_types=1);

namespace App\Controllers;

use App\TemplateResponse;

class ErrorController
{
    public function index(): TemplateResponse
    {
        return new TemplateResponse("404");
    }

}