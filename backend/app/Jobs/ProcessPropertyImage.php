<?php

namespace App\Jobs;

use App\Enums\ImageStatus;
use App\Models\PropertyImage;
use GdImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Retraite une photo téléversée : réencodage WebP (supprime EXIF et GPS), 3 tailles, suppression du fichier brut.
 */
class ProcessPropertyImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $imageId, public readonly string $originalPath) {}

    public function handle(): void
    {
        $image = PropertyImage::with('property')->find($this->imageId);
        if ($image === null) {
            return;
        }

        $originals = Storage::disk(config('chezvous.images.originals_disk'));
        $public = Storage::disk(config('chezvous.images.disk'));

        try {
            ini_set('memory_limit', '768M');
            $source = $this->load($originals->path($this->originalPath));
            $sizes = [];
            foreach (config('chezvous.images.sizes') as $name => $maxSide) {
                $target = "listings/{$image->property->ulid}/{$image->ulid}-{$name}.webp";
                $public->put($target, $this->encode($source, $maxSide), 'public');
                $sizes[$name] = $target;
            }
            $image->forceFill(['sizes' => $sizes, 'path' => $sizes['large'], 'status' => ImageStatus::Ready])->save();
        } catch (Throwable $e) {
            // Ne jamais journaliser le contenu du fichier : seulement l'identifiant et la nature de l'erreur.
            Log::warning("Traitement d'image échoué (id {$this->imageId}) : ".$e->getMessage());
            $image->forceFill(['status' => ImageStatus::Failed])->save();
        } finally {
            $originals->delete($this->originalPath);
            if (isset($source)) {
                unset($source);
            }
        }

        $this->ensureCover($image);
    }

    private function load(string $file): GdImage
    {
        $info = getimagesize($file);
        if ($info === false || ! in_array($info['mime'], config('chezvous.images.allowed_mimes'), true)) {
            throw new \RuntimeException('Format non pris en charge.');
        }
        if (config('chezvous.images.max_pixels') < $info[0] * $info[1]) {
            throw new \RuntimeException('Image trop grande.');
        }

        $image = imagecreatefromstring((string) file_get_contents($file));
        if ($image === false) {
            throw new \RuntimeException('Image illisible.');
        }

        // GD ignore l'orientation EXIF : on la applique avant de perdre les métadonnées.
        if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($file)['Orientation'] ?? 1;
            $image = match ($orientation) {
                3 => imagerotate($image, 180, 0) ?: $image,
                6 => imagerotate($image, -90, 0) ?: $image,
                8 => imagerotate($image, 90, 0) ?: $image,
                default => $image,
            };
        }

        return $image;
    }

    private function encode(GdImage $source, int $maxSide): string
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagewebp($canvas, null, config('chezvous.images.quality'));

        return (string) ob_get_clean();
    }

    /** Garantit une couverture prête : si la couverture a échoué, la première photo prête la remplace. */
    private function ensureCover(PropertyImage $image): void
    {
        $property = $image->property;
        $hasReadyCover = $property->images()->where('is_cover', true)->where('status', ImageStatus::Ready->value)->exists();
        if ($hasReadyCover) {
            return;
        }

        $candidate = $property->images()->where('status', ImageStatus::Ready->value)->first();
        if ($candidate !== null) {
            $property->images()->where('is_cover', true)->update(['is_cover' => false]);
            $candidate->forceFill(['is_cover' => true])->save();
        }
    }
}
