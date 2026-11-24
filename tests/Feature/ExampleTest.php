<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_erp_application_page_loads_successfully(): void
    {
        $response = $this->get('/erp');

        $response->assertOk();
    }
}