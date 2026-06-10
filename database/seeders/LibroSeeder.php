<?php

namespace Database\Seeders;

use App\Models\GestionAcademica\Libro;
use App\Models\GestionAcademica\Materia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LibroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $librosDir = storage_path('app/public/libros');
        if (!File::exists($librosDir)) {
            File::makeDirectory($librosDir, 0755, true);
        }

        $basePath = base_path();

        $map = [
            'ING-100' => ['file' => 'Ingles para hispanohablantes.pdf', 'title' => 'Inglés para Hispanohablantes'],
            'MAT-100' => ['file' => 'Matematica_preuniversitaria.pdf', 'title' => 'Matemáticas Preuniversitaria'],
            'COM-100' => ['file' => 'Computación preuniversitaria.pdf', 'title' => 'Computación Preuniversitaria'],
            'FIS-100' => ['file' => 'Física básica.pdf', 'title' => 'Física Básica'],
        ];

        foreach ($map as $materiaCod => $data) {
            $materia = Materia::where('codigo', $materiaCod)->first();
            if ($materia) {
                $source = $basePath . DIRECTORY_SEPARATOR . $data['file'];
                $destName = time() . '_' . str_replace(' ', '_', $data['file']);
                $dest = $librosDir . DIRECTORY_SEPARATOR . $destName;

                if (File::exists($source)) {
                    File::copy($source, $dest);
                    Libro::create([
                        'materia_id' => $materia->id,
                        'titulo' => $data['title'],
                        'archivo_path' => 'libros/' . $destName,
                    ]);
                }
            }
        }
    }
}
