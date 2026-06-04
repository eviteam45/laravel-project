<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_to_the_frontend(): void
    {
        $this->get('/')->assertRedirect();
    }

    public function test_api_errors_are_json_without_an_accept_header(): void
    {
        $this->get('/api/this-route-does-not-exist')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['message']);
    }
}
