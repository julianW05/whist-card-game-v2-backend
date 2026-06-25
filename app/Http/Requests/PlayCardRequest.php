<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlayCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'game_deck_id' => ['required', 'integer', 'exists:game_decks,id'],
        ];
    }
}
