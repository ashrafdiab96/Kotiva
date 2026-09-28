<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded product image into the two renditions §7.2 asks for:
 * a 1200px WebP for the product page and a 600px WebP thumbnail.
 *
 * Written against GD rather than pulling in an image library, because GD and
 * imagewebp() are already available and this is the only image work the
 * application does. The conversion is deliberately not left to the browser:
 * Filament's client-side resize would make the stored file depend on which
 * browser the admin happened to use, and would do nothing at all for an
 * upload that arrives by any other path.
 *
 * Images smaller than a target width are never upscaled — enlarging a small
 * source produces a soft image that looks worse than the original at the same
 * byte cost.
 */
final class ProductImageService
{
    public const FULL_WIDTH = 1200;

    public const THUMB_WIDTH = 600;

    /**
     * WebP quality. 82 is the point where the catalog's flat-lit product shots
     * stop showing ringing around the bottle edges.
     */
    private const QUALITY = 82;

    /**
     * Store an upload as WebP and return the disk-relative path of the full
     * rendition. The thumbnail sits alongside it under thumbs/.
     */
    public function store(
        UploadedFile $file,
        string $directory = 'products',
        string $disk = 'public',
    ): string {
        $source = $this->decode((string) $file->get());

        $base = $this->basename($file);
        $directory = trim($directory, '/');

        $fullPath = $directory.'/'.$base.'.webp';
        $thumbPath = $directory.'/thumbs/'.$base.'.webp';

        Storage::disk($disk)->put($fullPath, $this->encode($source, self::FULL_WIDTH));
        Storage::disk($disk)->put($thumbPath, $this->encode($source, self::THUMB_WIDTH));

        imagedestroy($source);

        return $fullPath;
    }

    /**
     * The thumbnail path for a stored full-size path, or null when the path is
     * a legacy catalog image that never had one generated.
     */
    public static function thumbnailFor(?string $fullPath): ?string
    {
        if ($fullPath === null || str_starts_with($fullPath, 'assets/')) {
            return null;
        }

        $dir = dirname($fullPath);
        $file = basename($fullPath);

        return ($dir === '.' ? '' : $dir.'/').'thumbs/'.$file;
    }

    /**
     * Remove a stored rendition and its thumbnail. Legacy catalog images are
     * committed brand assets and are never deleted by this.
     */
    public function delete(?string $fullPath, string $disk = 'public'): void
    {
        if ($fullPath === null || str_starts_with($fullPath, 'assets/')) {
            return;
        }

        $thumb = self::thumbnailFor($fullPath);

        Storage::disk($disk)->delete(array_filter([$fullPath, $thumb]));
    }

    private function decode(string $contents): GdImage
    {
        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The uploaded file is not a readable image.');
        }

        return $image;
    }

    /**
     * Resize to fit the target width and encode as WebP.
     */
    private function encode(GdImage $source, int $targetWidth): string
    {
        $width = imagesx($source);
        $height = imagesy($source);

        // Never upscale: a 400px source stays 400px.
        $newWidth = min($targetWidth, $width);
        $newHeight = (int) max(1, round($height * ($newWidth / $width)));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('Could not allocate an image canvas.');
        }

        // Preserve transparency — without these a PNG with an alpha channel
        // comes out with a black background.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, self::QUALITY);
        $encoded = (string) ob_get_clean();

        imagedestroy($canvas);

        if ($encoded === '') {
            throw new RuntimeException('WebP encoding produced no output.');
        }

        return $encoded;
    }

    /**
     * A stable, collision-resistant, URL-safe base name that still says what
     * the file is — "vitamin-c-serum-a1b2c3d4" rather than a bare hash.
     */
    private function basename(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($original);

        if ($slug === '') {
            $slug = 'product-image';
        }

        return Str::limit($slug, 60, '').'-'.Str::lower(Str::random(8));
    }
}
