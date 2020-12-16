<?php

namespace Madnest\MadstoreCSOB\Tests;

use Orchestra\Testbench\TestCase;
use Madnest\MadstoreCSOB\MadstoreCSOBServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [MadstoreCSOBServiceProvider::class];
    }

    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
