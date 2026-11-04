<?php
namespace Tests\Fixture;
use Tests\TestCase;

class HttpTestCasesTest extends TestCase {
    /**
     * A basic test example.
     * Stub for test.
     * @return void 
     */
    public function testBasicHttpResp()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
