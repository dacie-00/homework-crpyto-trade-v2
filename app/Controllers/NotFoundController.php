<?php
declare(strict_types=1);

namespace App\Controllers;

class NotFoundController
{
    public function index()
    {
        return ["404.html.twig", []];
    }

}