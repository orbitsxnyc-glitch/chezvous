<?php

namespace App\Services\Listings;

use App\Enums\ImageStatus;
use App\Exceptions\ListingRuleException;
use App\Jobs\ProcessPropertyImage;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Valide le contenu réel du fichier (pas l'extension ni le type annoncé), le stocke en privé sous un nom
     * généré, puis délègue le traitement à la file d'attente.
     */
    public function add(Property $property, UploadedFile $file): PropertyImage
    {
        $max = config('chezvous.listings.max_images');
        if ($property->images()->count() >= $max) {
            throw new ListingRuleException('too_many_images', "Maximum {$max} photos par annonce.");
        }

        $info = @getimagesize($file->getRealPath());
        $mime = $info === false ? null : $info['mime'];
        if ($mime === null || ! in_array($mime, config('chezvous.images.allowed_mimes'), true)) {
            throw new ListingRuleException('invalid_image', 'Format de photo non pris en charge (JPEG, PNG ou WebP).');
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $original = 'property-images/'.Str::ulid().'.'.$ext;

        return DB::transaction(function () use ($property, $file, $original) {
            // Verrou du bien : deux envois simultanés ne créent pas deux couvertures ni deux positions identiques.
            Property::whereKey($property->id)->lockForUpdate()->first();
            Storage::disk(config('chezvous.images.originals_disk'))->putFileAs(dirname($original), $file, basename($original));

            $image = PropertyImage::create([
                'property_id' => $property->id,
                'path' => '',
                'status' => ImageStatus::Processing,
                'position' => (int) $property->images()->max('position') + 1,
                'is_cover' => ! $property->images()->where('is_cover', true)->exists(),
            ]);
            ProcessPropertyImage::dispatch($image->id, $original)->afterCommit();

            return $image;
        });
    }

    /** @param list<string> $orderedUlids */
    public function reorder(Property $property, array $orderedUlids): void
    {
        $owned = $property->images()->pluck('ulid')->all();
        if (count($orderedUlids) !== count($owned) || array_diff($orderedUlids, $owned) !== [] || count(array_unique($orderedUlids)) !== count($orderedUlids)) {
            throw new ListingRuleException('invalid_order', 'La liste doit contenir exactement les photos de l\'annonce.');
        }

        DB::transaction(function () use ($property, $orderedUlids) {
            foreach ($orderedUlids as $position => $ulid) {
                $property->images()->where('ulid', $ulid)->update(['position' => $position + 1]);
            }
        });
    }

    public function setCover(Property $property, PropertyImage $image): void
    {
        DB::transaction(function () use ($property, $image) {
            $property->images()->where('is_cover', true)->update(['is_cover' => false]);
            $image->forceFill(['is_cover' => true])->save();
        });
    }

    public function remove(Property $property, PropertyImage $image): void
    {
        DB::transaction(function () use ($property, $image) {
            $wasCover = $image->is_cover;
            $disk = Storage::disk(config('chezvous.images.disk'));
            foreach ($image->sizes ?? [] as $path) {
                $disk->delete($path);
            }
            $image->delete();

            if ($wasCover) {
                $next = $property->images()->where('status', ImageStatus::Ready->value)->first() ?? $property->images()->first();
                $next?->forceFill(['is_cover' => true])->save();
            }
        });
    }

    /** @return array<string, string> URLs publiques par taille. */
    public function urls(PropertyImage $image): array
    {
        $disk = Storage::disk(config('chezvous.images.disk'));

        return collect($image->sizes ?? [])->map(fn (string $path) => $disk->url($path))->all();
    }
}
