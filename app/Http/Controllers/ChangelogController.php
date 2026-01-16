<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;

class ChangelogController extends Controller
{
    /**
     * Display the changelog page.
     */
    public function index(): Response
    {
        $changelogPath = base_path('CHANGELOG.md');

        if (! file_exists($changelogPath)) {
            return Inertia::render('Changelog', [
                'releases' => [],
                'error' => true,
            ]);
        }

        $markdown = file_get_contents($changelogPath);
        $releases = $this->parseReleases($markdown);

        return Inertia::render('Changelog', [
            'releases' => $releases,
            'error' => false,
        ]);
    }

    /**
     * Parse the changelog markdown into individual releases.
     */
    private function parseReleases(string $markdown): array
    {
        // Remove comments
        $markdown = preg_replace('/<!--[\s\S]*?-->/', '', $markdown);

        // Split by release headers (## [version] - date)
        // Match: ## [1.0.0] - 2026-01-16
        $pattern = '/^## \[([^\]]+)\] - (\d{4}-\d{2}-\d{2})/m';

        preg_match_all($pattern, $markdown, $matches, \PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return [];
        }

        $releases = [];
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $totalMatches = count($matches[0]);

        for ($i = 0; $i < $totalMatches; $i++) {
            $version = $matches[1][$i][0];
            $dateStr = $matches[2][$i][0];

            // Get the content between this header and the next (or end)
            $startPos = $matches[0][$i][1];
            $headerLength = strlen($matches[0][$i][0]);
            $contentStart = $startPos + $headerLength;

            if (isset($matches[0][$i + 1])) {
                $endPos = $matches[0][$i + 1][1];
                $content = substr($markdown, $contentStart, $endPos - $contentStart);
            } else {
                $content = substr($markdown, $contentStart);
            }

            // Clean up the content (remove horizontal rules)
            $content = preg_replace('/^---+\s*$/m', '', $content);
            $content = trim($content);

            try {
                $html = $converter->convert($content)->getContent();
            } catch (CommonMarkException) {
                $html = '<p>Error parsing release content.</p>';
            }

            // Format the date nicely
            $formattedDate = Carbon::parse($dateStr)->format('F j, Y');

            $releases[] = [
                'version' => $version,
                'date' => $formattedDate,
                'content' => $html,
            ];
        }

        return $releases;
    }
}
