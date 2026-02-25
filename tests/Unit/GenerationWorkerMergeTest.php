<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Workers\GenerationWorker;
use ReflectionClass;

class GenerationWorkerMergeTest extends TestCase
{
    private GenerationWorker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create instance without constructor dependencies (we only test mergeResumeConfig)
        $this->worker = (new ReflectionClass(GenerationWorker::class))
            ->newInstanceWithoutConstructor();
    }

    public function test_static_fields_from_llm_are_ignored(): void
    {
        $baseConfig = [
            'name' => 'Lucas from DB',
            'age' => '27 anos',
            'marital_status' => 'casado',
            'location' => 'Americana, SP',
            'phone_link' => '123456789',
            'phone' => '(19) 736273623',
            'email' => 'real@email.com',
            'github' => 'https://github.com/real',
            'github_display' => 'github.com/real',
            'linkedin' => 'https://linkedin.com/in/real',
            'linkedin_display' => 'linkedin.com/in/real',
            'objective' => 'Backend Dev',
            'skills' => ['PHP', 'Laravel'],
        ];

        $llmConfig = [
            // Static fields — should be IGNORED
            'name' => 'LLM Invented Name',
            'age' => '30 anos',
            'email' => 'fake@email.com',
            'phone' => '(00) 00000-0000',
            'phone_link' => '5500000000000',
            'github' => 'https://github.com/fake',
            'github_display' => 'github.com/fake',
            'linkedin' => 'https://linkedin.com/in/fake',
            'linkedin_display' => 'linkedin.com/in/fake',
            'location' => 'São Paulo, SP',
            'marital_status' => 'solteiro',
            // Non-static fields — should be MERGED
            'objective' => 'Senior Backend Developer',
            'skills' => ['PHP', 'Laravel', 'Docker', 'RabbitMQ'],
            'subtitle' => 'Adapted subtitle for this job',
        ];

        $result = $this->worker->mergeResumeConfig($baseConfig, $llmConfig);

        // Static fields must retain database values
        $this->assertEquals('Lucas from DB', $result['name']);
        $this->assertEquals('27 anos', $result['age']);
        $this->assertEquals('casado', $result['marital_status']);
        $this->assertEquals('Americana, SP', $result['location']);
        $this->assertEquals('123456789', $result['phone_link']);
        $this->assertEquals('(19) 736273623', $result['phone']);
        $this->assertEquals('real@email.com', $result['email']);
        $this->assertEquals('https://github.com/real', $result['github']);
        $this->assertEquals('github.com/real', $result['github_display']);
        $this->assertEquals('https://linkedin.com/in/real', $result['linkedin']);
        $this->assertEquals('linkedin.com/in/real', $result['linkedin_display']);

        // Non-static fields must use LLM values
        $this->assertEquals('Senior Backend Developer', $result['objective']);
        $this->assertEquals(['PHP', 'Laravel', 'Docker', 'RabbitMQ'], $result['skills']);
        $this->assertEquals('Adapted subtitle for this job', $result['subtitle']);
    }

    public function test_merge_without_static_fields_in_llm(): void
    {
        $baseConfig = [
            'name' => 'Lucas',
            'email' => 'real@email.com',
            'objective' => 'Backend Dev',
            'skills' => ['PHP'],
        ];

        $llmConfig = [
            'objective' => 'Full-Stack Dev',
            'skills' => ['PHP', 'React'],
        ];

        $result = $this->worker->mergeResumeConfig($baseConfig, $llmConfig);

        $this->assertEquals('Lucas', $result['name']);
        $this->assertEquals('real@email.com', $result['email']);
        $this->assertEquals('Full-Stack Dev', $result['objective']);
        $this->assertEquals(['PHP', 'React'], $result['skills']);
    }

    public function test_merge_with_empty_llm_config(): void
    {
        $baseConfig = [
            'name' => 'Lucas',
            'email' => 'real@email.com',
            'objective' => 'Backend Dev',
        ];

        $result = $this->worker->mergeResumeConfig($baseConfig, []);

        $this->assertEquals($baseConfig, $result);
    }

    public function test_merge_with_empty_base_config(): void
    {
        $llmConfig = [
            'name' => 'LLM Name',       // static — should be stripped
            'email' => 'fake@email.com', // static — should be stripped
            'objective' => 'Dev',        // non-static — should be kept
        ];

        $result = $this->worker->mergeResumeConfig([], $llmConfig);

        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertEquals('Dev', $result['objective']);
    }
}
