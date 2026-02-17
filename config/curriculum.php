<?php

return [
    // Caminho do template Blade a ser usado para geração do currículo
    'template' => 'curriculum/base',

    // Dados padrão do candidato (podem ser sobrescritos)
    'default_candidate' => [
        'name' => '',
        'subtitle' => '',
        'age' => '',
        'marital_status' => '',
        'location' => '',
        'phone' => '',
        'phone_link' => '',
        'email' => '',
        'github' => '',
        'github_display' => '',
        'linkedin' => '',
        'linkedin_display' => '',
        'objective' => '',
        'skills' => [],
        'languages' => [],
        'experience' => [],
        'education' => [],
        'projects' => [],
        'certificates' => []
    ],
];
