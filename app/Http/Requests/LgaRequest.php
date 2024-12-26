<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LgaRequest extends FormRequest
{



    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "lgaName"=>"required|unique:lgas,lga_name"
        ];
    }
    public function messages():array
    {
        return [
            "lgaName.required"=>"LGA name is required",
            "lgaName.unique"=>"LGA with this name already exists.",
        ];
    }

}
