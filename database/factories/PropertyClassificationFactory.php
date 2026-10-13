<?php

namespace Database\Factories;

use App\Models\PropertyClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyClassificationFactory extends Factory
{
    protected $model = PropertyClassification::class;

    public function definition()
    {
        return [
            'class_name' => $this->faker->word,
        ];
    }
}
