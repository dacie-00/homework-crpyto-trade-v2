<?php
declare(strict_types=1);

namespace App\Controllers;

class ErrorController
{
    public function index()
    {
        return ["404", []];
    }

}