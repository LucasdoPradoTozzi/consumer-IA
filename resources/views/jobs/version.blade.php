@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Versão da Vaga #{{ $version->id }} - {{ $job->title }}</h1>
    <h3>Aplicações para esta versão</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Candidato</th>
                <th>Status</th>
                <th>Data</th>
                <th>Versões</th>
            </tr>
        </thead>
        <tbody>
            @foreach($version->applications as $application)
            <tr>
                <td>{{ $application->id }}</td>
                <td>{{ $application->candidate_name ?? '-' }}</td>
                <td>{{ $application->status ?? '-' }}</td>
                <td>{{ $application->created_at }}</td>
                <td>
                    @foreach($application->versions as $appVersion)
                    <a href="{{ route('job-applications.version.show', [$application->id, $appVersion->id]) }}" class="btn btn-outline-primary btn-sm">Versão #{{ $appVersion->id }}</a>
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <a href="{{ route('jobs.show', $job->id) }}" class="btn btn-secondary">Voltar</a>
</div>
@endsection