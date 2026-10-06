<?php

namespace Tests\Feature;

use App\Models\BcDemoEntity;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PmdDocumentFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existing_preregistrations_remain_unchanged_when_document_policy_is_configured(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }

        $this->assertTrue(Schema::hasColumns('preregistrations', [
            'document_submission_method', 'documentation_status', 'documentation_deadline',
        ]));
        $legacy = DB::table('preregistrations')->orderBy('id')->first();
        $this->assertNotNull($legacy);
        DB::table('preregistrations')->where('id', $legacy->id)->update([
            'document_submission_method' => null,
            'documentation_status' => null,
            'documentation_deadline' => null,
        ]);
        $before = DB::table('preregistrations')->where('id', $legacy->id)->first();
        $this->assertNotNull($before);
        $this->assertNull($before->document_submission_method);
        $this->assertNull($before->documentation_status);
        $this->assertNull($before->documentation_deadline);

        $type = DB::table('preregistration_document_types')->insertGetId([
            'name' => 'Documento de teste', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('process_document_types')->insert([
            'process_id' => $before->process_id, 'document_type_id' => $type,
            'required' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $configured = DB::table('process_document_types')->where('process_id', $before->process_id)
            ->where('document_type_id', $type)->firstOrFail();
        $this->assertTrue($configured->required);

        $after = DB::table('preregistrations')->where('id', $before->id)->firstOrFail();
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->protocol, $after->protocol);
        $this->assertNull($after->document_submission_method);
    }
}
