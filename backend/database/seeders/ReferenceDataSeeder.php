<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Locality;
use App\Models\ModerationReason;
use App\Models\PropertyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Référentiels de lancement (Dakar). Idempotent : peut être relancé sans doublon.
 *
 * ATTENTION : les points de repère des quartiers sont APPROXIMATIFS et à valider ;
 * les contours (`geom`) ne sont pas fournis. La hiérarchie communale détaillée reste à compléter.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->localities();
        $this->propertyTypes();
        $this->amenities();
        $this->moderationReasons();
    }

    private function localities(): void
    {
        $region = $this->locality(null, 'region', 'Dakar', 'DK');
        $departments = [];
        foreach (['Dakar', 'Pikine', 'Guédiawaye', 'Rufisque', 'Keur Massar'] as $name) {
            $departments[$name] = $this->locality($region->id, 'department', $name);
        }

        // Quartiers de la phase 1 de lancement : nom, code de référence, latitude, longitude (approximatifs).
        $neighborhoods = [
            ['Yoff', 'YOF', 14.7497, -17.4730],
            ['Ouakam', 'OUK', 14.7227, -17.4903],
            ['Mamelles', 'MAM', 14.7261, -17.4977],
            ['Almadies', 'ALM', 14.7435, -17.5143],
            ['Ngor', 'NGR', 14.7517, -17.5138],
            ['Mermoz', 'MRZ', 14.7062, -17.4685],
            ['Sacré-Cœur', 'SCO', 14.7110, -17.4637],
            ['VDN', 'VDN', 14.7200, -17.4700],
            ['Keur Gorgui', 'KGO', 14.7123, -17.4680],
            ['Liberté', 'LIB', 14.7114, -17.4627],
        ];
        foreach ($neighborhoods as [$name, $code, $lat, $lon]) {
            $this->locality($departments['Dakar']->id, 'neighborhood', $name, $code, $lat, $lon);
        }
    }

    private function locality(?int $parentId, string $level, string $name, ?string $code = null, ?float $lat = null, ?float $lon = null): Locality
    {
        $locality = Locality::updateOrCreate(
            ['slug' => Str::slug($level === 'neighborhood' ? $name : $name.'-'.$level)],
            ['parent_id' => $parentId, 'level' => $level, 'name' => $name, 'code' => $code, 'is_active' => true],
        );
        if ($lat !== null && $lon !== null) {
            DB::statement('UPDATE localities SET centroid = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?', [$lon, $lat, $locality->id]);
        }

        return $locality;
    }

    private function propertyTypes(): void
    {
        $types = ['apartment' => 'Appartement', 'house' => 'Maison / villa', 'studio' => 'Studio', 'room' => 'Chambre',
            'land' => 'Terrain', 'office' => 'Bureau', 'shop' => 'Local commercial', 'warehouse' => 'Entrepôt', 'building' => 'Immeuble'];
        $sort = 0;
        foreach ($types as $code => $label) {
            PropertyType::updateOrCreate(['code' => $code], ['label' => $label, 'sort' => ++$sort]);
        }
    }

    private function amenities(): void
    {
        $amenities = ['parking' => 'Parking', 'security' => 'Gardiennage', 'air_conditioning' => 'Climatisation',
            'water' => 'Eau courante', 'electricity' => 'Électricité', 'furnished' => 'Meublé', 'generator' => 'Groupe électrogène',
            'balcony' => 'Balcon / terrasse', 'elevator' => 'Ascenseur', 'internet' => 'Internet / fibre'];
        $sort = 0;
        foreach ($amenities as $code => $label) {
            Amenity::updateOrCreate(['code' => $code], ['label' => $label, 'sort' => ++$sort]);
        }
    }

    private function moderationReasons(): void
    {
        $reasons = [
            ['incomplete', 'Informations incomplètes', 'rejection'],
            ['bad_photos', 'Photos insuffisantes ou non conformes', 'rejection'],
            ['duplicate', 'Annonce en doublon', 'rejection'],
            ['price_inconsistent', 'Prix incohérent', 'rejection'],
            ['prohibited_content', 'Contenu interdit', 'rejection'],
            ['not_available', 'Bien indisponible', 'report'],
            ['fraud', 'Suspicion de fraude', 'report'],
            ['wrong_info', 'Informations erronées', 'report'],
            ['inappropriate', 'Contenu inapproprié', 'report'],
        ];
        foreach ($reasons as [$code, $label, $kind]) {
            ModerationReason::updateOrCreate(['code' => $code], ['label' => $label, 'kind' => $kind]);
        }
    }
}
