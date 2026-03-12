<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

abstract class AdminController extends Controller
{
    protected function renderPage(string $view, array $page, array $data = []): View
    {
        return view($view, $data + [
            'pageTitle' => $page['title'],
            'pageHeading' => $page['heading'] ?? $page['title'],
            'pageDescription' => $page['description'] ?? null,
            'pageKicker' => $page['kicker'] ?? 'Admin',
        ]);
    }
}
