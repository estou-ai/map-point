<?php

namespace Estouai\MapPoint\Tests;

use Estouai\MapPoint\ServiceProvider;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function userWithCpAccess()
    {
        $user = User::make()->email('editor@example.com')->set('super', true);
        $user->save();

        return $user;
    }
}
