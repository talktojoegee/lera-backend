<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PropertyClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PropertyClassificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the _getClass method with different inputs.
     */
    public function test_get_class_method()
    {
        // Seed the database with test data
        PropertyClassification::factory()->create(['class_name' => 'Kogi State Govt.']);
        PropertyClassification::factory()->create(['class_name' => 'Commercial']);
        PropertyClassification::factory()->create(['class_name' => 'Education (Private)']);
        PropertyClassification::factory()->create(['class_name' => 'Hospital']);
        PropertyClassification::factory()->create(['class_name' => 'Vacant Properties & Open Land']);
        PropertyClassification::factory()->create(['class_name' => 'Residential']);

        // Create an instance of the class where _getClass is defined
        $instance = new \App\Services\PropertyService(); // Adjust based on where _getClass is defined

        // Test for "government"
        $result = $instance->_getClass('government');
        $this->assertNotNull($result);
        $this->assertEquals('Kogi State Govt.', $result->class_name);

        // Test for "commercial"
        $result = $instance->_getClass('commercial');
        $this->assertNotNull($result);
        $this->assertEquals('Commercial', $result->class_name);

        // Test for "educational"
        $result = $instance->_getClass('educational');
        $this->assertNotNull($result);
        $this->assertEquals('Education (Private)', $result->class_name);

        // Test for "health"
        $result = $instance->_getClass('health');
        $this->assertNotNull($result);
        $this->assertEquals('Hospital', $result->class_name);

        // Test for "open land"
        $result = $instance->_getClass('open land');
        $this->assertNotNull($result);
        $this->assertEquals('Vacant Properties & Open Land', $result->class_name);

        // Test for "residential"
        $result = $instance->_getClass('residential');
        $this->assertNotNull($result);
        $this->assertEquals('Residential', $result->class_name);

        // Test for invalid input
        $result = $instance->_getClass('invalid_class_name');
        $this->assertNull($result);
    }
}
