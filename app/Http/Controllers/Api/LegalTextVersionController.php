<?php

namespace App\Http\Controllers\Api;

use App\Enums\LegalTextType;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalTextVersionResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Legal texts')]
class LegalTextVersionController extends Controller
{
    #[Endpoint(title: 'List version history', description: 'Returns all published versions of a legal text, newest first.')]
    public function index(#[CurrentUser] User $user, LegalTextType $type): AnonymousResourceCollection
    {
        $legalText = $user->legalTexts()->where('type', $type)->firstOrFail();

        return LegalTextVersionResource::collection(
            $legalText->versions()->published()->latest('version')->paginate(),
        );
    }
}
