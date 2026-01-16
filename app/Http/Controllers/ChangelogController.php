<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ChangelogService;
use Inertia\Inertia;
use Inertia\Response;

class ChangelogController extends Controller
{
    public function __construct(
        private readonly ChangelogService $changelogService
    ) {}

    /**
     * Display the changelog page.
     */
    public function index(): Response
    {
        $data = $this->changelogService->getReleases();

        return Inertia::render('Changelog', $data);
    }
}
