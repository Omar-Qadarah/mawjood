<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminProfileController extends Controller
{
    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=1,min_height=1'],
        ]);

        $disk = Storage::disk('local');
        $path = null;
        if (isset($data['avatar'])) {
            $path = $disk->putFile('admin-avatars', $data['avatar']);
            abort_if($path === false, 503, 'Could not store the profile photo.');
        }

        try {
            [$user, $oldPath] = DB::transaction(function () use ($request, $data, $path): array {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $oldPath = $user->avatar_path;
                $user->name = $data['name'];
                if ($path !== null) {
                    $user->avatar_path = $path;
                }
                $user->save();

                return [$user, $oldPath];
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($path !== null) {
                $disk->delete($path);
            }

            throw $exception;
        }

        if ($path !== null && $oldPath !== null) {
            $disk->delete($oldPath);
        }

        $request->user()->setRawAttributes($user->getAttributes(), true);

        return UserResource::make($user);
    }

    public function avatar(Request $request): StreamedResponse
    {
        $path = $request->user()->avatar_path;
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
