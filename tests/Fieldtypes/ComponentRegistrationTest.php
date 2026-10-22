<?php

namespace Estouai\MapPoint\Tests\Fieldtypes;

use Estouai\MapPoint\Fieldtypes\GeoPoint;
use Estouai\MapPoint\Tests\TestCase;

class ComponentRegistrationTest extends TestCase
{
    /**
     * The control panel resolves a field's Vue component as
     * `${config.type}-fieldtype`, using the handle verbatim — it does not
     * convert underscores to hyphens. A registration name that disagrees with
     * the handle renders "Component <handle>-fieldtype does not exist".
     */
    public function test_the_registered_component_name_matches_the_fieldtype_handle()
    {
        $source = file_get_contents(__DIR__.'/../../resources/js/cp.js');

        preg_match_all("/Statamic\.component\(\s*'([^']+)'/", $source, $matches);

        $this->assertNotEmpty($matches[1], 'cp.js registers no components.');
        $this->assertContains(GeoPoint::handle().'-fieldtype', $matches[1]);
    }
}
