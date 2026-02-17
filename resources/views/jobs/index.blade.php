@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Vagas</h1>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Empresa</th>
                <th>Criada em</th>
                <th>Versões</th>
            </tr>
        </thead>
        <tbody>
            @foreach($jobs as $job)
            <tr>
                <td>{{ $job->id }}</td>
                <td>{{ $job->title }}</td>
                <td>{{ $job->company }}</td>
                <td>{{ $job->created_at }}</td>
                <td>
                    <a href="{{ route('jobs.show', $job->id) }}" class="btn btn-primary btn-sm">Ver versões</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection