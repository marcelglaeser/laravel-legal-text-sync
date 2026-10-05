<?php

namespace App\Http\Controllers\Api;

use App\Enums\LegalTextType;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalTextResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Legal texts')]
class LegalTextController extends Controller
{
    #[Endpoint(title: 'List current legal texts', description: 'Returns the currently published version of every legal text of the merchant.')]
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $legalTexts = $user->legalTexts()
            ->has('currentVersion')
            ->with('currentVersion')
            ->orderBy('type')
            ->get();

        return LegalTextResource::collection($legalTexts);
    }

    #[Endpoint(title: 'Show current legal text')]
    public function show(#[CurrentUser] User $user, LegalTextType $type): LegalTextResource
    {
        $legalText = $user->legalTexts()
            ->where('type', $type)
            ->has('currentVersion')
            ->with('currentVersion')
            ->firstOrFail();

        return LegalTextResource::make($legalText);
    }
}
