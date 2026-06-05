<?php

namespace Database\Factories;

use App\Models\InscripcionPagos\Documento;
use App\Models\InscripcionPagos\Inscripcion;
use App\Models\Seguridad\User;
use App\Support\States\DocumentoState;
use App\Support\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    public function definition(): array
    {
        return [
            'inscripcion_id' => Inscripcion::factory(),
            'tipo' => fake()->randomElement(TipoDocumento::OBLIGATORIOS),
            'numero' => fake()->numerify('DOC-#####'),
            'archivo_path' => null, // Omitimos archivo físico para simplicidad del CU03
            'estado' => DocumentoState::PENDIENTE,
            'observacion' => null,
            'revisado_por' => null,
            'revisado_en' => null,
        ];
    }

    public function revisadoPor(User $user, string $estado = DocumentoState::APROBADO, string $observacion = null): static
    {
        return $this->state(fn () => [
            'estado' => $estado,
            'observacion' => $observacion,
            'revisado_por' => $user->id,
            'revisado_en' => now(),
        ]);
    }
}
