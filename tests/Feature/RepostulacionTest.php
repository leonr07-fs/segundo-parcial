<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @deprecated Las repostulaciones autenticadas fueron reemplazadas por el flujo público.
 * Ver RepostulacionPublicaTest.
 */
class RepostulacionTest extends TestCase
{
    public function test_ruta_autenticada_de_repostulacion_fue_retirada(): void
    {
        $this->postJson('/api/postulantes/repostular', [])
            ->assertNotFound();
    }
}
