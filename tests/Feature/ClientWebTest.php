<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Comprueba que el cliente web estático existe y mantiene las prácticas
 * seguras que exige el enunciado (fetch + JSON + textContent, sin innerHTML).
 */
class ClientWebTest extends TestCase
{
    public function test_static_api_client_exists_and_uses_safe_rendering(): void
    {
        $htmlPath = public_path('cliente/index.html');
        $jsPath = public_path('cliente/app.js');
        $cssPath = public_path('cliente/styles.css');

        $this->assertFileExists($htmlPath);
        $this->assertFileExists($jsPath);
        $this->assertFileExists($cssPath);

        $html = (string) file_get_contents($htmlPath);
        $js = (string) file_get_contents($jsPath);
        $css = (string) file_get_contents($cssPath);

        $this->assertStringContainsString('/cliente/styles.css', $html);
        $this->assertStringContainsString('fetch', $js);
        $this->assertStringContainsString('JSON.stringify', $js);
        $this->assertStringContainsString('Content-Type', $js);
        $this->assertStringContainsString('textContent', $js);
        $this->assertStringNotContainsString('innerHTML', $js);
        $this->assertStringContainsString('@media', $css);
        $this->assertStringContainsString('max-width: 420px', $css);
    }
}
