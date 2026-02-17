@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Versão da Aplicação #{{ $version->id }}</h1>
    <h3>Detalhes</h3>
    <ul>
        <li><strong>Status:</strong> {{ $version->completed ? 'Completa' : 'Incompleta' }}</li>
        <li><strong>E-mail enviado:</strong> {{ $version->email_sent ? 'Sim' : 'Não' }}</li>
        <li><strong>Data:</strong> {{ $version->created_at }}</li>
    </ul>
    <h4>Carta de Apresentação</h4>
    <pre>{{ $version->cover_letter }}</pre>
    <h4>Currículo Ajustado</h4>
    <pre>{{ is_array($version->resume_data) ? json_encode($version->resume_data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : $version->resume_data }}</pre>
    <h4>PDFs</h4>
    @if($version->resume_path)
    <a href="{{ asset('storage/' . $version->resume_path) }}" target="_blank" class="btn btn-success">Ver Currículo PDF</a>
    @endif
    @if($version->cover_letter_pdf_path ?? false)
    <a href="{{ asset('storage/' . $version->cover_letter_pdf_path) }}" target="_blank" class="btn btn-success">Ver Carta PDF</a>
    @endif
    <br><br>
    <a href="{{ url()->previous() }}" class="btn btn-secondary">Voltar</a>
</div>
@endsection