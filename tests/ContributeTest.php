<?php

// use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ContributeTest extends TestCase {

    /**
     * A basic functional test example.
     *
     * @return void
     */
    public function testContribute()
    {
        $response = $this->call('GET', '/contribute/');

        if (env('CONTRIB_ENABLED', false))
        {
            $this->assertEquals(200, $response->getStatusCode());
        }
        else
        {
            $this->assertEquals(200, $response->getStatusCode());
        }
    }
}
