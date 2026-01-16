<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Collection;

class VendorDeduplicationService
{
    /**
     * Common email domains to exclude from domain matching.
     */
    private const COMMON_EMAIL_DOMAINS = [
        'gmail.com',
        'yahoo.com',
        'hotmail.com',
        'outlook.com',
        'aol.com',
        'icloud.com',
    ];

    /**
     * Company suffixes to remove during normalization.
     */
    private const COMPANY_SUFFIXES = [
        'llc',
        'inc',
        'corp',
        'co',
        'ltd',
        'company',
        'corporation',
        'incorporated',
        'limited',
    ];

    /**
     * Find potential duplicate vendor pairs based on similarity.
     *
     * @param  float  $threshold  Minimum similarity score (0-1)
     * @param  int  $limit  Maximum number of results
     * @return array Array of potential duplicate pairs with similarity scores
     */
    public function findPotentialDuplicates(float $threshold = 0.6, int $limit = 50): array
    {
        $vendors = Vendor::canonical()
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'contact_name', 'email', 'phone', 'vendor_trades']);

        return $this->findDuplicatesInCollection($vendors, $threshold, $limit);
    }

    /**
     * Find potential duplicates within a given collection of vendors.
     *
     * @param  Collection  $vendors  Collection of vendors to compare
     * @param  float  $threshold  Minimum similarity score (0-1)
     * @param  int  $limit  Maximum number of results
     * @return array Array of potential duplicate pairs
     */
    public function findDuplicatesInCollection(Collection $vendors, float $threshold = 0.6, int $limit = 50): array
    {
        $potentialDuplicates = [];
        $processedPairs = [];

        foreach ($vendors as $i => $vendor1) {
            foreach ($vendors->slice($i + 1) as $vendor2) {
                $pairKey = $this->getPairKey($vendor1->id, $vendor2->id);

                if (isset($processedPairs[$pairKey])) {
                    continue;
                }

                $similarity = $this->calculateSimilarity($vendor1, $vendor2);

                if ($similarity >= $threshold) {
                    $potentialDuplicates[] = [
                        'vendor1' => $vendor1,
                        'vendor2' => $vendor2,
                        'similarity' => round($similarity, 3),
                        'match_reasons' => $this->getMatchReasons($vendor1, $vendor2),
                    ];
                    $processedPairs[$pairKey] = true;
                }
            }
        }

        // Sort by similarity descending
        usort($potentialDuplicates, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Limit results
        return array_slice($potentialDuplicates, 0, $limit);
    }

    /**
     * Calculate similarity score between two vendors.
     *
     * @return float Similarity score between 0 and 1
     */
    public function calculateSimilarity(Vendor $vendor1, Vendor $vendor2): float
    {
        $scores = [];

        // Company name similarity (50% weight)
        $nameScore = $this->calculateNameSimilarity($vendor1->company_name, $vendor2->company_name);
        if ($nameScore !== null) {
            $scores[] = $nameScore * 0.5;
        }

        // Phone match (25% weight for exact match)
        if ($this->phonesMatch($vendor1->phone, $vendor2->phone)) {
            $scores[] = 0.25;
        }

        // Email match (15% for exact, 5% for same domain)
        $emailScore = $this->calculateEmailSimilarity($vendor1->email, $vendor2->email);
        if ($emailScore > 0) {
            $scores[] = $emailScore;
        }

        // Contact name similarity (10% weight if > 70% similar)
        $contactScore = $this->calculateNameSimilarity($vendor1->contact_name, $vendor2->contact_name);
        if ($contactScore !== null && $contactScore > 0.7) {
            $scores[] = $contactScore * 0.1;
        }

        return array_sum($scores);
    }

    /**
     * Get human-readable reasons why two vendors might be duplicates.
     */
    public function getMatchReasons(Vendor $vendor1, Vendor $vendor2): array
    {
        $reasons = [];

        // Company name
        $nameSimilarity = $this->calculateNameSimilarity($vendor1->company_name, $vendor2->company_name);
        if ($nameSimilarity !== null && $nameSimilarity > 0.6) {
            $reasons[] = 'Similar company names ('.round($nameSimilarity * 100).'% match)';
        }

        // Phone
        if ($this->phonesMatch($vendor1->phone, $vendor2->phone)) {
            $reasons[] = 'Same phone number';
        }

        // Email
        if ($vendor1->email && $vendor2->email) {
            if (strtolower($vendor1->email) === strtolower($vendor2->email)) {
                $reasons[] = 'Same email address';
            }
        }

        // Contact name
        $contactSimilarity = $this->calculateNameSimilarity($vendor1->contact_name, $vendor2->contact_name);
        if ($contactSimilarity !== null && $contactSimilarity > 0.8) {
            $reasons[] = 'Similar contact names';
        }

        return $reasons;
    }

    /**
     * Normalize a string for comparison.
     * Removes common company suffixes, special characters, and normalizes whitespace.
     */
    public function normalizeString(string $str): string
    {
        $str = strtolower(trim($str));

        // Remove common suffixes
        $pattern = '/\b('.implode('|', self::COMPANY_SUFFIXES).')\b/';
        $str = preg_replace($pattern, '', $str);

        // Remove special characters
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);

        // Collapse whitespace
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);
    }

    /**
     * Calculate similarity between two names.
     *
     * @return float|null Similarity score (0-1) or null if either name is empty
     */
    private function calculateNameSimilarity(?string $name1, ?string $name2): ?float
    {
        if (empty($name1) || empty($name2)) {
            return null;
        }

        $normalized1 = $this->normalizeString($name1);
        $normalized2 = $this->normalizeString($name2);

        similar_text($normalized1, $normalized2, $similarity);

        return $similarity / 100;
    }

    /**
     * Check if two phone numbers match (ignoring formatting).
     */
    private function phonesMatch(?string $phone1, ?string $phone2): bool
    {
        if (empty($phone1) || empty($phone2)) {
            return false;
        }

        $normalized1 = preg_replace('/\D/', '', $phone1);
        $normalized2 = preg_replace('/\D/', '', $phone2);

        return $normalized1 === $normalized2 && strlen($normalized1) >= 10;
    }

    /**
     * Calculate email similarity score.
     *
     * @return float Score: 0.15 for exact match, 0.05 for same company domain, 0 otherwise
     */
    private function calculateEmailSimilarity(?string $email1, ?string $email2): float
    {
        if (empty($email1) || empty($email2)) {
            return 0;
        }

        $email1 = strtolower($email1);
        $email2 = strtolower($email2);

        // Exact match
        if ($email1 === $email2) {
            return 0.15;
        }

        // Check for same company domain
        $domain1 = $this->extractEmailDomain($email1);
        $domain2 = $this->extractEmailDomain($email2);

        if ($domain1 && $domain1 === $domain2 && ! in_array($domain1, self::COMMON_EMAIL_DOMAINS, true)) {
            return 0.05;
        }

        return 0;
    }

    /**
     * Extract domain from email address.
     */
    private function extractEmailDomain(string $email): ?string
    {
        $pos = strrpos($email, '@');

        if ($pos === false) {
            return null;
        }

        $domain = substr($email, $pos + 1);

        return $domain !== '' ? $domain : null;
    }

    /**
     * Get a consistent pair key for two vendor IDs.
     */
    private function getPairKey(string $id1, string $id2): string
    {
        return $id1 < $id2 ? "{$id1}:{$id2}" : "{$id2}:{$id1}";
    }
}
