<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Libro extends Model
{
    use HasFactory;

    protected $fillable = [
        'materia_id',
        'titulo',
        'archivo_path',
    ];

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }
}
