<?php

namespace App\Rules;

use App\Models\User;
use App\Models\UserDetail;

use Illuminate\Contracts\Validation\Rule;


class EmailUpdateProfile implements Rule
{

    public function passes($attribute, $value)
    {
        // Get the user ID from the request
        $userId = request('user_id');

        // Find the user by user ID
        $user = User::find($userId);

        // Check if the provided email is equal to the user's current email
        if ($user && $user->email === $value) {
            return true; // Validation passes
        }

        // Check if the provided email doesn't belong to any user in the database
        $existingUser = User::where('email', $value)->first();
        if (!$existingUser) {
            return true; // Validation passes
        }

        // Check if the provided email is not equal to any other user's email in the database
        if ($existingUser->id !== $userId) {
            return false; // Validation fails
        }

        return true; // Validation passes if none of the above conditions are met
    }


    public function message()
    {
        return 'The :attribute has already been taken ' ;
    }
}
