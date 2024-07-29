<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateDocumentationCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Asegurarse de que no exista un archivo CONTRIBUTING.md previo
        if (File::exists(base_path('CONTRIBUTING.md'))) {
            File::delete(base_path('CONTRIBUTING.md'));
        }
    }

    protected function tearDown(): void
    {
        // Limpiar después de la prueba
        if (File::exists(base_path('CONTRIBUTING.md'))) {
            File::delete(base_path('CONTRIBUTING.md'));
        }

        parent::tearDown();
    }

    public function testGenerateDocumentationCommand()
    {
        // Ejecutar el comando
        Artisan::call('documenter:generate');

        // Verificar que el comando se ejecutó correctamente
        $this->assertStringContainsString('Documentation generated successfully!', Artisan::output());

        // Verificar que se creó el archivo CONTRIBUTING.md
        $this->assertTrue(File::exists(base_path('CONTRIBUTING.md')));

        // Verificar el contenido del archivo CONTRIBUTING.md
        $content = File::get(base_path('CONTRIBUTING.md'));
        $this->assertStringContainsString('Models', $content);
        $this->assertStringContainsString('Filament Resources', $content);
        $this->assertStringContainsString('API Controllers', $content);
        $this->assertStringContainsString('Jobs', $content);
        $this->assertStringContainsString('Events', $content);
        $this->assertStringContainsString('Middleware', $content);
        $this->assertStringContainsString('Rules', $content);
    }

    public function testGenerateDocumentationForSpecificComponent()
    {
        // Ejecutar el comando para un componente específico
        Artisan::call('documenter:generate', ['--component' => 'model']);

        // Verificar que el comando se ejecutó correctamente
        $this->assertStringContainsString('Model documentation generated.', Artisan::output());

        // Verificar que se creó el archivo CONTRIBUTING.md
        $this->assertTrue(File::exists(base_path('CONTRIBUTING.md')));

        // Verificar el contenido del archivo CONTRIBUTING.md
        $content = File::get(base_path('CONTRIBUTING.md'));
        $this->assertStringContainsString('Models', $content);
        $this->assertStringNotContainsString('Filament Resources', $content);
    }

    public function testGenerateDocumentationWithCustomOutput()
    {
        $customPath = storage_path('app/custom_contributing.md');

        // Ejecutar el comando con una ruta de salida personalizada
        Artisan::call('documenter:generate', ['--output' => $customPath]);

        // Verificar que el comando se ejecutó correctamente
        $this->assertStringContainsString('Documentation generated successfully!', Artisan::output());

        // Verificar que se creó el archivo en la ruta personalizada
        $this->assertTrue(File::exists($customPath));

        // Limpiar
        File::delete($customPath);
    }
}