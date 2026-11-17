<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_redirects_guests_to_sign_in(): void
    {
        $this->get('/')->assertRedirect('/admin/login');
    }
}
