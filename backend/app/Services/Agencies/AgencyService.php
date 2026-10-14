<?php

namespace App\Services\Agencies;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AgencyService
{
    private const EDITABLE = ['name', 'description', 'address', 'locality_id', 'contact_phone', 'contact_email', 'contact_whatsapp', 'ninea', 'rccm'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** Crée l'organisation et en fait le gérant (AUTH-05). */
    public function createFor(User $manager, string $name): Agency
    {
        return DB::transaction(function () use ($manager, $name) {
            $agency = new Agency(['name' => $name, 'slug' => $this->uniqueSlug($name)]);
            $agency->forceFill(['created_by' => $manager->id])->save();

            AgencyMember::create([
                'agency_id' => $agency->id, 'user_id' => $manager->id, 'role' => MemberRole::Manager,
                'status' => MemberStatus::Active, 'can_see_all' => true, 'joined_at' => now(),
            ]);
            $this->audit->record('agency.created', $agency, null, ['manager' => $manager->ulid], $manager->id);

            return $agency;
        });
    }

    /**
     * @param  array<string, mixed>  $data  champs validés
     */
    public function update(Agency $agency, User $actor, array $data): Agency
    {
        return DB::transaction(function () use ($agency, $actor, $data) {
            $agency->fill(array_intersect_key($data, array_flip(self::EDITABLE)));
            $changed = array_keys($agency->getDirty());
            $agency->save();

            if (array_key_exists('zone_ids', $data)) {
                $agency->zones()->sync($data['zone_ids']);
                $changed[] = 'zones';
            }
            if ($changed !== []) {
                // Seuls les noms des champs sont journalisés (NINEA, RCCM et contacts sont des données sensibles).
                $this->audit->record('agency.updated', $agency, null, ['fields' => $changed], $actor->id);
            }

            return $agency->refresh();
        });
    }

    /** Logo : contenu réel contrôlé, réencodé en WebP (métadonnées supprimées), 512 px au plus. */
    public function setLogo(Agency $agency, UploadedFile $file, User $actor): Agency
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! in_array($info['mime'], config('chezvous.images.allowed_mimes'), true) || config('chezvous.images.max_pixels') < $info[0] * $info[1]) {
            throw new RuntimeException('invalid_image');
        }
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source instanceof GdImage) {
            throw new RuntimeException('invalid_image');
        }

        $scale = min(1, 512 / max(imagesx($source), imagesy($source)));
        $w = max(1, (int) round(imagesx($source) * $scale));
        $h = max(1, (int) round(imagesy($source) * $scale));
        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, imagesx($source), imagesy($source));
        ob_start();
        imagewebp($canvas, null, config('chezvous.images.quality'));
        $webp = (string) ob_get_clean();

        $disk = Storage::disk(config('chezvous.images.disk'));
        if ($agency->logo_path) {
            $disk->delete($agency->logo_path);
        }
        $path = "agencies/{$agency->ulid}/logo-".Str::random(8).'.webp';
        $disk->put($path, $webp, 'public');
        $agency->forceFill(['logo_path' => $path])->save();
        $this->audit->record('agency.logo_updated', $agency, null, null, $actor->id);

        return $agency;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agence';
        $slug = $base;
        $i = 2;
        while (Agency::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
