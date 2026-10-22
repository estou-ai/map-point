<?php

namespace Estouai\MapPoint\Tests;

use Estouai\MapPoint\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
