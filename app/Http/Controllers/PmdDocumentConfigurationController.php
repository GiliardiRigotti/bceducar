<?php

namespace App\Http\Controllers;

use App\Models\LegacySchool;
use App\User;
use iEducar\Packages\PreMatricula\Models\Process as PmdProcess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PmdDocumentConfigurationController extends Controller
{
    private function activeAdministrator(User $user): void
    {
        abort_unless($user->isActive() && ($user->isAdmin() || $user->isInstitutional()), 403);
        abort_unless(Schema::hasTable('process_document_types'), 503, 'A migração documental do PMD ainda não foi aplicada.');
    }

    private function permittedProcesses(User $user)
    {
        $query = PmdProcess::query();
        if ($user->isAdmin()) {
            return $query;
        }

        $schools = LegacySchool::query()->where('ref_cod_instituicao', $user->ref_cod_instituicao)
            ->select('cod_escola');

        return $query->whereExists(function ($query) use ($schools) {
            $query->selectRaw('1')->from('process_school')
                ->whereColumn('process_school.process_id', 'processes.id')
                ->whereIn('process_school.school_id', $schools);
        })->whereNotExists(function ($query) use ($schools) {
            $query->selectRaw('1')->from('process_school')
                ->whereColumn('process_school.process_id', 'processes.id')
                ->whereNotIn('process_school.school_id', $schools);
        });
    }

    private function process(User $user, int $id): PmdProcess
    {
        return $this->permittedProcesses($user)->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $this->activeAdministrator($request->user());
        $processes = $this->permittedProcesses($request->user())->orderBy('name')->get(['id', 'name', 'documentation_deadline', 'document_workflow_enabled', 'documentation_configured_by', 'documentation_configured_at']);
        $process = $request->integer('process') ? $this->process($request->user(), $request->integer('process')) : $processes->first();
        $types = DB::table('preregistration_document_types')->orderBy('name')->get();
        $policy = $process ? DB::table('process_document_types')->where('process_id', $process->id)
            ->pluck('required', 'document_type_id') : collect();

        return view('bc-registration.configuration', compact('processes', 'process', 'types', 'policy'));
    }

    public function createType(Request $request): RedirectResponse
    {
        $this->activeAdministrator($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('preregistration_document_types', 'code')],
        ]);
        DB::table('preregistration_document_types')->insert([
            'name' => trim($data['name']), 'description' => $data['description'] ?? null,
            'code' => $data['code'], 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Tipo documental cadastrado.');
    }

    public function updateType(Request $request, int $type): RedirectResponse
    {
        $this->activeAdministrator($request->user());
        // The catalog is shared by processes. Only global administrators may change a shared definition.
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['required', 'boolean'],
        ]);
        $updated = DB::table('preregistration_document_types')->where('id', $type)->update([
            'name' => trim($data['name']), 'description' => $data['description'] ?? null,
            'active' => $data['active'], 'updated_at' => now(),
        ]);
        abort_unless($updated, 404);

        return back()->with('status', 'Tipo documental atualizado.');
    }

    public function updateProcess(Request $request, int $process): RedirectResponse
    {
        $this->activeAdministrator($request->user());
        $process = $this->process($request->user(), $process);
        $data = $request->validate([
            'documentation_deadline' => ['nullable', 'date'],
            'documentation_delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'physical_delivery_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'physical_retry_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'documentation_retry_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'document_workflow_enabled' => ['sometimes', 'required', Rule::in(['0', '1', 'legacy'])],
            'documents' => ['array'],
            'documents.*' => ['array'],
            'documents.*.enabled' => ['nullable', 'boolean'],
            'documents.*.required' => ['nullable', 'boolean'],
        ]);
        if (($data['document_workflow_enabled'] ?? null) === 'legacy' && $process->document_workflow_enabled !== null) {
            abort(422, 'Após uma decisão explícita, selecione ativar ou suspender novas liberações.');
        }
        $types = DB::table('preregistration_document_types')->where('active', true)->whereNotNull('code')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submitted = $data['documents'] ?? [];
        foreach ($submitted as $id => $selection) {
            if (!ctype_digit((string) $id) || !in_array((int) $id, $types, true)) {
                abort(422, 'Tipo documental inexistente ou inativo.');
            }
        }

        if (in_array($data['document_workflow_enabled'] ?? null, [true, 1, '1'], true)
            && !collect($submitted)->contains(fn ($selection) => (bool) ($selection['enabled'] ?? false))) {
            abort(422, 'Selecione ao menos um tipo documental ativo para ativar o processo.');
        }
        DB::transaction(function () use ($process, $data, $types, $submitted) {
            DB::table('processes')->where('id', $process->id)->update([
                'documentation_deadline' => !empty($data['documentation_delivery_days']) ? null : ($data['documentation_deadline'] ?? null),
                'documentation_delivery_days' => array_key_exists('documentation_delivery_days', $data) ? $data['documentation_delivery_days'] : $process->documentation_delivery_days,
                'physical_delivery_days' => $data['physical_delivery_days'] ?? $process->physical_delivery_days ?? 7,
                'physical_retry_days' => $data['physical_retry_days'] ?? $process->physical_retry_days ?? 3,
                'documentation_retry_days' => $data['documentation_retry_days'] ?? $process->documentation_retry_days ?? 3,
                'document_workflow_enabled' => array_key_exists('document_workflow_enabled', $data)
                    ? ($data['document_workflow_enabled'] === 'legacy' ? null : (bool) $data['document_workflow_enabled'])
                    : $process->document_workflow_enabled,
                'documentation_configured_by' => auth()->id(), 'documentation_configured_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('process_document_types')->where('process_id', $process->id)
                ->whereIn('document_type_id', $types)->delete();
            foreach ($types as $id) {
                $selection = $submitted[$id] ?? null;
                if (!$selection || !($selection['enabled'] ?? false)) {
                    continue;
                }
                DB::table('process_document_types')->insert([
                    'process_id' => $process->id, 'document_type_id' => $id,
                    'required' => (bool) ($selection['required'] ?? false),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        Log::info('Configuração documental do processo atualizada', [
            'process_id' => $process->id, 'actor_id' => $request->user()->getKey(),
            'previous_activation' => $process->document_workflow_enabled,
            'activation' => $data['document_workflow_enabled'] ?? 'unchanged',
            'delivery_days' => $data['documentation_delivery_days'] ?? $process->documentation_delivery_days,
            'retry_days' => $data['documentation_retry_days'] ?? $process->documentation_retry_days,
            'physical_delivery_days' => $data['physical_delivery_days'] ?? $process->physical_delivery_days,
            'physical_retry_days' => $data['physical_retry_days'] ?? $process->physical_retry_days,
        ]);

        return redirect()->route('bc-registration.configuration', ['process' => $process->id])
            ->with('status', 'Exigências documentais do processo atualizadas.');
    }
}
