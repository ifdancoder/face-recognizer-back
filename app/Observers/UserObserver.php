<?php

namespace App\Observers;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UserObserver
{
    public function creating(User $user)
    {
        if (empty($user->password)) {
            $user->password = Hash::make(Str::random(8));
        }
    }
}