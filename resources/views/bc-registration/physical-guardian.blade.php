@if($application->status !== \App\EnrollmentRequests\RequestStatus::Registered && !$application->status->terminal() && ($application->approved_at || $application->intermediate_registration_id))
<section class="card"><h2>Matrícula em confirmação</h2>
@if(!$application->approved_at)
<p>A escola solicitou uma regularização. Siga as orientações abaixo e aguarde nova conferência.</p>
@elseif($application->integration_status === 'INTEGRATED')
<p>Sua documentação digital foi aprovada. Para concluir a matrícula, apresente os documentos físicos na unidade escolar.</p>
@else
<p>Sua documentação digital foi aprovada. A escola está concluindo a integração cadastral. Aguarde a orientação para a apresentação física.</p>
@endif
@if($application->physical_deadline?->lt(now()) && !$application->physical_confirmed_at)
<p role="status">O prazo inicial de apresentação terminou. Confira os prazos de regularização abaixo ou procure a escola. A solicitação não foi cancelada automaticamente.</p>
@endif
@if($application->physical_deadline)<p>Prazo para apresentação: <strong>{{ $application->physical_deadline->format('d/m/Y H:i') }}</strong>.</p>@endif
@php
$physicalPendencies = \Illuminate\Support\Facades\DB::table('bc_physical_reviews')->where('registration_request_id', $application->id)->where('status', 'PENDING')->get();
@endphp
@foreach($physicalPendencies as $pendency)<div class="row"><strong>{{ $pendency->subject === 'cadastro' ? 'Ficha cadastral' : ($application->documents->firstWhere('id', (int) substr($pendency->subject, 9))?->documentName() ?? 'Documento físico') }}</strong><p>{{ $pendency->reason }}</p>@if($pendency->deadline)<p>Regularizar até {{ \Illuminate\Support\Carbon::parse($pendency->deadline)->format('d/m/Y H:i') }}. Apresente a documentação corrigida à escola para nova conferência.</p>@endif</div>@endforeach
</section>
@endif
