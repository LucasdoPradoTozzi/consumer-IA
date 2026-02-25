<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>{{ $candidate['name'] ?? 'Candidate' }} - CV</title>
    <!-- Font Awesome for external link icon -->
    <style>
        @media print {
            @page {
                margin: 0;
                size: A4;
            }

            body {
                margin: 0;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            color: #222;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .page {
            width: 100%;
            background: #fff;
            padding: 0 10px;
        }

        h1 {
            font-size: 24px;
            margin: 0;
            font-weight: bold;
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 14px;
            margin-top: 2px;
        }

        .personal-info {
            margin-top: 6px;
            font-size: 13px;
            line-height: 1.3;
        }

        .links a {
            color: #000;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .section {
            margin-top: 14px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .divider {
            border-top: 1px solid #000;
            margin-bottom: 4px;
        }

        ul {
            margin: 4px 0 0 16px;
            padding: 0;
        }

        li {
            margin-bottom: 3px;
            line-height: 1.2;
            font-size: 13px;
        }

        .job {
            margin-bottom: 10px;
        }

        .job-title {
            font-weight: bold;
            font-size: 13px;
        }

        .job-company {
            font-size: 12px;
            margin-top: 1px;
        }

        .job-period {
            font-size: 11px;
            font-style: italic;
            margin-bottom: 2px;
        }

        .two-columns {
            width: 100%;
            clear: both;
        }

        .column {
            flex: 1;
        }

        .small-text {
            font-size: 11px;
        }

        .label {
            font-weight: bold;
            color: #222;
            margin-right: 2px;
        }

        .value {
            color: #222;
        }

        .external-link {
            text-decoration: none;
            color: #222;
        }

        .external-link:hover {
            text-decoration: underline;
        }

        .centered {
            text-align: center;
            width: 100%;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <div class="page">
        <div class="centered">
            @if(!empty($candidate['name']))
            <h1>{{ $candidate['name'] }}</h1>
            @endif
            @if(!empty($candidate['subtitle']))
            <div class="subtitle">{{ $candidate['subtitle'] }}</div>
            @endif
            <div class="personal-info">
                @if(!empty($candidate['age']) || !empty($candidate['marital_status']))
                <div>{{ $candidate['age'] ?? '' }}{{ !empty($candidate['marital_status']) ? (!empty($candidate['age']) ? ', ' : '') . $candidate['marital_status'] : '' }}</div>
                @endif
                @if(!empty($candidate['location']))
                <div>{{ $candidate['location'] }}</div>
                @endif
                @if(!empty($candidate['phone']))
                <div><span class="label">Celular:</span> <span class="value"><a class="external-link" href="https://wa.me/{{ $candidate['phone_link'] ?? '' }}" target="_blank" rel="noopener noreferrer">{{ $candidate['phone'] }}</a></span></div>
                @endif
                @if(!empty($candidate['email']))
                <div><span class="label">E-mail:</span> <span class="value"><a class="external-link" href="mailto:{{ $candidate['email'] }}" target="_blank" rel="noopener noreferrer">{{ $candidate['email'] }}</a></span></div>
                @endif
                @if(!empty($candidate['github']) || !empty($candidate['github_display']))
                <div><span class="label">GitHub:</span> <span class="value"><a class="external-link" href="{{ $candidate['github'] ?? '' }}" target="_blank" rel="noopener noreferrer">{{ $candidate['github_display'] ?? '' }}</a></span></div>
                @endif
                @if(!empty($candidate['linkedin']) || !empty($candidate['linkedin_display']))
                <div><span class="label">LinkedIn:</span> <span class="value"><a class="external-link" href="{{ $candidate['linkedin'] ?? '' }}" target="_blank" rel="noopener noreferrer">{{ $candidate['linkedin_display'] ?? '' }}</a></span></div>
                @endif
            </div>
        </div>

        @if(!empty($candidate['objective']))
        <div class="section">
            <div class="section-title">Objetivo</div>
            <div class="divider"></div>
            <div class="small-text">{{ $candidate['objective'] }}</div>
        </div>
        @endif

        @if(!empty($candidate['skills']))
        <div class="section">
            <div class="section-title">Habilidades</div>
            <div class="divider"></div>
            <div class="skills-list" style="word-break: break-word; white-space: normal; font-size: 13px;">
                {{ implode(', ', $candidate['skills']) }}
            </div>
        </div>
        @endif

        @if(!empty($candidate['languages']))
        <div class="section">
            <div class="section-title">Idiomas</div>
            <div class="divider"></div>
            <ul>
                @foreach($candidate['languages'] as $lang)
                <li>{{ $lang }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(!empty($candidate['experience']))
        <div class="section">
            <div class="section-title">Experiência</div>
            <div class="divider"></div>
            @foreach($candidate['experience'] as $job)
            <div class="job">
                <div class="job-title">{{ $job['title'] ?? '' }}</div>
                <div class="job-company">{{ $job['company'] ?? '' }}</div>
                <div class="job-period">{{ $job['period'] ?? '' }}</div>
                @if(!empty($job['details']))
                <ul>
                    @foreach($job['details'] as $detail)
                    <li>{{ $detail }}</li>
                    @endforeach
                </ul>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if(!empty($candidate['education']))
        <div class="section">
            <div class="section-title">Educação</div>
            <div class="divider"></div>
            @foreach($candidate['education'] as $edu)
            <div class="job">
                <div class="job-title">{{ $edu['title'] ?? '' }}</div>
                <div class="job-company">{{ $edu['company'] ?? '' }}</div>
                <div class="job-period">{{ $edu['period'] ?? '' }}</div>
                @if(!empty($edu['details']))
                <ul>
                    @foreach($edu['details'] as $detail)
                    <li>{{ $detail }}</li>
                    @endforeach
                </ul>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if(!empty($candidate['projects']))
        <div class="section">
            <div class="section-title">Projetos Pessoais</div>
            <div class="divider"></div>
            <ul>
                @foreach($candidate['projects'] as $proj)
                <li><strong>{{ $proj['name'] ?? '' }}</strong> — {{ $proj['description'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(!empty($candidate['certificates']))
        <div class="section">
            <div class="section-title">Certificados</div>
            <div class="divider"></div>
            <ul>
                @foreach($candidate['certificates'] as $cert)
                <li>{{ $cert }}</li>
                @endforeach
            </ul>
        </div>
        @endif

    </div>

</body>

</html>