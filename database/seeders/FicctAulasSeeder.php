<?php

namespace Database\Seeders;

use App\Support\FicctAulas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FicctAulasSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $aulas = array_map(fn (array $aula) => [
            'codigo' => $aula['codigo'],
            'nombre' => $aula['nombre'],
            'capacidad' => $aula['capacidad'],
            'ubicacion' => $aula['ubicacion'],
            'activa' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], FicctAulas::catalogo());

        DB::table('aulas')->upsert($aulas, ['codigo'], ['nombre', 'capacidad', 'ubicacion', 'activa', 'updated_at']);
        DB::table('aulas')->whereNotIn('codigo', FicctAulas::TODAS)->update(['activa' => false, 'updated_at' => $now]);

        $aulaIds = DB::table('aulas')->whereIn('codigo', FicctAulas::TODAS)->pluck('id', 'codigo')->all();
        $baseCodigos = [...FicctAulas::TEORICAS, FicctAulas::AUDITORIO];

        $grupos = DB::table('grupos')->orderBy('gestion_id')->orderBy('codigo')->get(['id']);
        foreach ($grupos->values() as $index => $grupo) {
            DB::table('grupos')->where('id', $grupo->id)->update([
                'aula_id' => $aulaIds[$baseCodigos[$index % count($baseCodigos)]],
                'updated_at' => $now,
            ]);
        }

        $grupoAulas = DB::table('grupos')->pluck('aula_id', 'id')->all();
        $grupoOrden = $grupos->values()->pluck('id')->flip()->all();

        $horarios = DB::table('horarios')
            ->join('grupo_materias', 'grupo_materias.id', '=', 'horarios.grupo_materia_id')
            ->join('materias', 'materias.id', '=', 'grupo_materias.materia_id')
            ->get([
                'horarios.id',
                'grupo_materias.grupo_id',
                'materias.codigo as materia_codigo',
                'materias.nombre as materia_nombre',
            ]);

        foreach ($horarios as $horario) {
            $esComputacion = FicctAulas::esComputacion((string) $horario->materia_codigo)
                || FicctAulas::esComputacion((string) $horario->materia_nombre);

            $aulaId = $esComputacion
                ? $aulaIds[FicctAulas::LABORATORIOS_COMPUTACION[($grupoOrden[$horario->grupo_id] ?? 0) % count(FicctAulas::LABORATORIOS_COMPUTACION)]]
                : ($grupoAulas[$horario->grupo_id] ?? null);

            DB::table('horarios')->where('id', $horario->id)->update([
                'aula_id' => $aulaId,
                'modalidad' => 'presencial',
                'updated_at' => $now,
            ]);
        }
    }
}
