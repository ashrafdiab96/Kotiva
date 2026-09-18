<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin avatars drawn locally, as an inline SVG of the person's initials.
 *
 * Filament's default fetches each avatar from ui-avatars.com, which sends
 * every admin's name to a third party on every page load. The picture is two
 * letters on a circle; there is no reason for it to leave the building.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));

        $initials = collect(preg_split('/\s+/u', $name) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $initials = htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Brand ink on white text, matching the storefront's --k-black.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="#231F20"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#FFFFFF" '
            .'font-family="Montserrat, Arial, sans-serif" font-size="26" font-weight="600">'.$initials.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
