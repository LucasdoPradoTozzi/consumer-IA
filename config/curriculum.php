<?php

return [
    // Caminho do template Blade a ser usado para geração do currículo
    'template' => 'curriculum/base',

    // Dados padrão do candidato (podem ser sobrescritos)
    'default_candidate' => [
        'name' => 'Lucas do Prado Tozzi Martins',
        'subtitle' => 'Desenvolvedor Backend - 2 anos de experiência',
        'age' => '27 anos',
        'marital_status' => 'casado',
        'location' => 'Americana, São Paulo, Brasil',
        'phone' => '(19) 99464-4182',
        'phone_link' => '5519994644182',
        'email' => 'lucaspradodev@gmail.com',
        'github' => 'https://github.com/LucasdoPradoTozzi',
        'github_display' => 'github.com/LucasdoPradoTozzi',
        'linkedin' => 'https://www.linkedin.com/in/lucas-do-prado-tozzi-dev-backend/',
        'linkedin_display' => 'linkedin.com/in/lucas-do-prado-tozzi-dev-backend',
        'objective' => 'Desenvolvedor Backend Pleno',
        'skills' => [
            'PHP',
            'JavaScript',
            'TypeScript',
            'NodeJS',
            'Slim',
            'Laravel',
            'Express',
            'jQuery',
            'MySQL',
            'PostgreSQL',
            'Redis',
            'RabbitMQ',
            'Docker',
            'Git',
            'RESTful APIs',
            'Microserviços',
            'Sistemas Distribuídos',
            'Frontend',
            'Backend',
            'CI/CD',
            'Linux',
            'Unit Testing',
            'TDD',
            'DDD',
            'OOP'
        ],
        'languages' => ['English (B2)', 'Português (Nativo)'],
        'experience' => [
            [
                'title' => 'Desenvolvedor Full-Stack',
                'company' => 'LetMeIn — Campinas, São Paulo, Brasil',
                'period' => '12/2023 - Presente',
                'details' => [
                    'Realizei o desenvolvimento de ponta a ponta de novas features e sistemas internos.',
                    'Arquitetei, implementei e mantive integrações entre sistemas diversos, garantindo fluxo de dados estável e interoperabilidade.',
                    'Mais de 540 cards finalizados no Jira.',
                    'Participei de análises e levantamento de requisitos, transformando necessidades de negócio em soluções técnicas.',
                    'Realizei queries avançadas no banco de dados, para analisar necessidades e identificação de problemas.',
                    'Conduzi a homologação e integração de equipamentos com o sistema principal e microserviços, validando a comunicação entre software e hardware.',
                    'Identifiquei e corrigi inconsistências em APIs de terceiros por meio de um sistema de validação que garante a integridade das informações, não havendo novos relatos após a implantação. A solução foi implementada estendendo a arquitetura existente, seguindo o princípio Open/Closed.',
                    'Arquitetei, desenvolvi e implantei uma nova API escalável com Laravel e RabbitMQ, substituindo a antiga integração legado. A integração atende 5 condomínios diferentes, 75 mil usuários, 19 mil veículos, eliminando os problemas de entradas duplicadas e garantindo a sincronização entre plataformas, além de permitir o mapeamento complexo de papéis entre os condomínios e sua base principal.'
                ]
            ]
        ],
        'education' => [
            [
                'title' => 'Tecnólogo em Análise e Desenvolvimento de Sistemas',
                'company' => 'Centro Paula Souza - FATEC — Americana, SP',
                'period' => '07/2023 - Presente (6º semestre)',
                'details' => [
                    'Foco nos princípios de Engenharia de Software, Design de Banco de Dados, e Desenvolvimento Web Full-Stack.'
                ]
            ]
        ],
        'projects' => [
            [
                'name' => 'snack-bar-manager',
                'description' => 'Aplicação web desenvolvida em Laravel com Livewire, com o objetivo de controlar estoque e vendas para um comércio pequeno. Este projeto foi criado especificamente para ajudar minha mãe a monitorar o estoque da sua cantina, assim como analisar os produtos mais vendidos, identificar quais produtos descontinuar a venda, permitindo que ela tome decisões baseadas em dados. Este projeto foi feito lado a lado com as necessidades do cliente, escutando as necessidades de customização que trariam uma melhor UI e UX.'
            ],
            [
                'name' => 'wedding-invite',
                'description' => 'Convite do meu próprio casamento desenvolvido como uma página estática, criado para suprir a necessidade de um meio prático e acessível de envio via WhatsApp. Publicado no GitHub Pages, o projeto utiliza Open Graph para gerar o card de visualização do convite e parâmetros de URL para personalizar dinamicamente o destinatário, tornando a entrega mais pessoal e eficiente.'
            ],
            [
                'name' => 'laravel-docker',
                'description' => 'Ambiente Docker estruturado para desenvolvimento de aplicações Laravel, criado com o objetivo de facilitar a inicialização de novos projetos e aprofundar o conhecimento em Docker. O setup simplifica a configuração do ambiente local, garante padronização entre ambientes de desenvolvimento e reduz o tempo de onboarding.'
            ]
        ],
        'certificates' => [
            'TOEIC English Certificate — 900 pontos (B2)',
            'Trilha de API — TDC São Paulo 2024',
            'Python Avançado — Alura'
        ]
    ],
];
