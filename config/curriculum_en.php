<?php

return [
    'template' => 'curriculum/base',
    'default_candidate' => [
        'name' => 'Lucas do Prado Tozzi Martins',
        'subtitle' => 'Backend Developer - 2 years of experience',
        'age' => '27 years old',
        'marital_status' => 'married',
        'location' => 'Americana, São Paulo, Brazil',
        'phone' => '(19) 99464-4182',
        'phone_link' => '5519994644182',
        'email' => 'lucaspradodev@gmail.com',
        'github' => 'https://github.com/LucasdoPradoTozzi',
        'github_display' => 'github.com/LucasdoPradoTozzi',
        'linkedin' => 'https://www.linkedin.com/in/lucas-do-prado-tozzi-dev-backend/',
        'linkedin_display' => 'linkedin.com/in/lucas-do-prado-tozzi-dev-backend',
        'objective' => 'Mid-level Backend Developer',
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
            'Microservices',
            'Distributed Systems',
            'Frontend',
            'Backend',
            'CI/CD',
            'Linux',
            'Unit Testing',
            'TDD',
            'DDD',
            'OOP'
        ],
        'languages' => ['English (B2)', 'Portuguese (Native)'],
        'experience' => [
            [
                'title' => 'Full-Stack Developer',
                'company' => 'LetMeIn — Campinas, São Paulo, Brazil',
                'period' => '12/2023 - Present',
                'details' => [
                    'Developed end-to-end new features and internal systems.',
                    'Architected, implemented, and maintained integrations between various systems, ensuring stable data flow and interoperability.',
                    'Completed over 540 Jira cards.',
                    'Participated in analysis and requirements gathering, transforming business needs into technical solutions.',
                    'Performed advanced database queries to analyze needs and identify issues.',
                    'Led homologation and integration of equipment with the main system and microservices, validating communication between software and hardware.',
                    'Identified and fixed inconsistencies in third-party APIs through a validation system that ensures data integrity, with no further reports after deployment. The solution was implemented by extending the existing architecture, following the Open/Closed principle.',
                    'Architected, developed, and deployed a new scalable API with Laravel and RabbitMQ, replacing the legacy integration. The integration serves 5 different condominiums, 75,000 users, 19,000 vehicles, eliminating duplicate entries and ensuring synchronization between platforms, as well as enabling complex role mapping between condominiums and their main database.'
                ]
            ]
        ],
        'education' => [
            [
                'title' => 'Technologist in Systems Analysis and Development',
                'company' => 'Centro Paula Souza - FATEC — Americana, SP',
                'period' => '07/2023 - Present (6th semester)',
                'details' => [
                    'Focus on Software Engineering principles, Database Design, and Full-Stack Web Development.'
                ]
            ]
        ],
        'projects' => [
            [
                'name' => 'snack-bar-manager',
                'description' => 'Web application developed in Laravel with Livewire, aimed at controlling inventory and sales for a small business. This project was specifically created to help my mother monitor her snack bar\'s inventory, analyze best-selling products, identify which products to discontinue, and make data-driven decisions. The project was built side-by-side with the client\'s needs, listening to customization requests that improved UI and UX.'
            ],
            [
                'name' => 'wedding-invite',
                'description' => 'My own wedding invitation developed as a static page, created to meet the need for a practical and accessible way to send via WhatsApp. Published on GitHub Pages, the project uses Open Graph to generate the invitation preview card and URL parameters to dynamically personalize the recipient, making delivery more personal and efficient.'
            ],
            [
                'name' => 'laravel-docker',
                'description' => 'Docker environment structured for Laravel application development, created to facilitate the initialization of new projects and deepen Docker knowledge. The setup simplifies local environment configuration, ensures standardization across development environments, and reduces onboarding time.'
            ]
        ],
        'certificates' => [
            'TOEIC English Certificate — 900 points (B2)',
            'API Track — TDC São Paulo 2024',
            'Advanced Python — Alura'
        ]
    ],
];
