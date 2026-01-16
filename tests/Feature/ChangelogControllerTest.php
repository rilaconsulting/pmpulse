<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_page_requires_authentication(): void
    {
        $response = $this->get('/changelog');

        $response->assertRedirect('/login');
    }

    public function test_changelog_page_displays_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/changelog');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Changelog')
            ->has('releases')
            ->where('error', false)
        );
    }

    public function test_changelog_page_contains_releases(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/changelog');

        $response->assertOk();

        // Get the inertia page data
        $page = $response->viewData('page');
        $releases = $page['props']['releases'];

        $this->assertNotEmpty($releases);
        $this->assertArrayHasKey('version', $releases[0]);
        $this->assertArrayHasKey('date', $releases[0]);
        $this->assertArrayHasKey('content', $releases[0]);
    }

    public function test_changelog_excludes_unreleased_section(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/changelog');

        $response->assertOk();

        // Get the inertia page data
        $page = $response->viewData('page');
        $releases = $page['props']['releases'];

        // Ensure no release has "Unreleased" as the version
        foreach ($releases as $release) {
            $this->assertNotEquals('Unreleased', $release['version']);
        }
    }

    public function test_changelog_formats_dates_nicely(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/changelog');

        $response->assertOk();

        // Get the inertia page data
        $page = $response->viewData('page');
        $releases = $page['props']['releases'];

        // Check that dates are formatted nicely (e.g., "January 16, 2026")
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-z]+ \d{1,2}, \d{4}$/',
            $releases[0]['date']
        );
    }
}
