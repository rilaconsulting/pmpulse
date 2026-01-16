<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;

class ChangelogService
{
    private readonly CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Get parsed releases from the changelog file.
     *
     * @return array{releases: array<int, array{version: string, date: string, content: string}>, error: bool}
     */
    public function getReleases(): array
    {
        $changelogPath = base_path('CHANGELOG.md');

        if (! file_exists($changelogPath) || ! is_readable($changelogPath)) {
            return ['releases' => [], 'error' => true];
        }

        $markdown = file_get_contents($changelogPath);

        if ($markdown === false) {
            return ['releases' => [], 'error' => true];
        }

        return [
            'releases' => $this->parseReleases($markdown),
            'error' => false,
        ];
    }

    /**
     * Parse the changelog markdown into individual releases.
     *
     * @return array<int, array{version: string, date: string, content: string}>
     */
    private function parseReleases(string $markdown): array
    {
        // Remove comments
        $markdown = preg_replace('/<!--[\s\S]*?-->/', '', $markdown) ?? $markdown;

        // Split by release headers (## [version] - date)
        // Match: ## [1.0.0] - 2026-01-16
        $pattern = '/^## \[([^\]]+)\] - (\d{4}-\d{2}-\d{2})/m';

        preg_match_all($pattern, $markdown, $matches, \PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return [];
        }

        $releases = [];
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
            $content = preg_replace('/^---+\s*$/m', '', $content) ?? $content;
            $content = trim($content);

            try {
                $html = $this->converter->convert($content)->getContent();
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
