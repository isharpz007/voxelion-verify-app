<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The welcome route only renders in local/dev environments.
        $this->app['env'] = 'local';
    }

    public function test_root_renders_welcome_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Start')
                ->where('linkState', 'valid')
                ->where('dev', true)
                ->where('task.reference', 'OP-2026-7842')
                ->where('task.partner', 'OpinionPadi'));
    }

    public function test_signed_handoff_renders_valid_task_state(): void
    {
        $token = $this->mintHandoff(['task_id' => 'OP-2026-9999']);

        $this->get('/start?t='.urlencode($token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Start')
                ->where('linkState', 'valid')
                ->where('task.reference', 'OP-2026-9999'));
    }

    public function test_invalid_link_shows_error_state(): void
    {
        $this->get('/start?t=garbage.token.here')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Start')
                ->where('linkState', 'malformed')
                ->where('task', null));
    }

    public function test_expired_link_shows_expired_state(): void
    {
        $token = $this->mintHandoff(['exp' => time() - 5]);

        $this->get('/start?t='.urlencode($token))
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'expired'));
    }
}
