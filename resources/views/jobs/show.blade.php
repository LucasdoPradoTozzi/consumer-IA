@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Vaga: {{ $job->title }} ({{ $job->company }})</h1>
    <h3>Versões da Vaga</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Data</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($job->versions as $version)
            <tr>
                <td>{{ $version->id }}</td>
                <td>{{ $version->created_at }}</td>
                <td>{{ $version->status ?? '-' }}</td>
                <td>
                    <a href="{{ route('jobs.version', [$job->id, $version->id]) }}" class="btn btn-info btn-sm">Ver aplicações</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <a href="{{ route('jobs.index') }}" class="btn btn-secondary">Voltar</a>
</div>
@endsection