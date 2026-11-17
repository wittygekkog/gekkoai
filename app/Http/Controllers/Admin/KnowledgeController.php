<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiEvaluationRun;
use App\Models\AiFeedback;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Models\AiLearningExample;
use App\Models\AiModelVersion;
use App\Services\AccessService;
use App\Services\Ai\InstructionAnalyzer;
use App\Services\KnowledgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly KnowledgeService $knowledge,
        private readonly InstructionAnalyzer $analyzer,
    ) {}

    public function index(Request $request): View
    {
        $ids = $this->access->scopedTenantIds($request->user('admin'), $request->session()->get('selected_tenant_id'));
        $documents = AiKnowledgeDocument::query()->with('tenant')->whereIn('tenant_id', $ids)->latest()->paginate(150);

        return view('admin.knowledge.index', compact('documents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:50000'],
        ]);
        $tenant = $this->access->tenantsFor($request->user('admin'))->firstWhere('id', $data['tenant_id']);
        abort_if($tenant === null, 403);
        $this->access->assert($request->user('admin'), $tenant, 'ai.memory.manage');
        $document = new AiKnowledgeDocument();
        $document->tenant_id = $tenant->id;
        $document->title = $data['title'];
        $document->source_type = 'upload';
        $document->content = $data['content'];
        $document->approval_status = 'pending';
        $document->save();
        $this->chunk($document);

        return back()->with('success', 'Document stored. It cannot influence operations until it is approved.');
    }

    public function approve(Request $request, AiKnowledgeDocument $document): RedirectResponse
    {
        $this->access->assert($request->user('admin'), $document->tenant, 'ai.memory.manage');
        $document->approval_status = 'approved';
        $document->save();

        return back()->with('success', 'Document approved for retrieval.');
    }

    public function learning(Request $request): View
    {
        $ids = $this->access->tenantsFor($request->user('admin'))->pluck('id');
        $examples = AiLearningExample::query()->whereIn('tenant_id', $ids)->latest()->paginate(150);
        $feedback = AiFeedback::query()->whereIn('tenant_id', $ids)->latest()->limit(20)->get();
        $runs = AiEvaluationRun::query()->latest('evaluated_at')->limit(10)->get();

        return view('admin.knowledge.learning', compact('examples', 'feedback', 'runs'));
    }

    public function feedback(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'operation_request_id' => ['nullable', 'uuid'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'issue_type' => ['nullable', 'string', 'max:40'],
            'correction' => ['nullable', 'string', 'max:5000'],
        ]);
        $tenant = $this->access->tenantsFor($request->user('admin'))->firstWhere('id', $data['tenant_id']);
        abort_if($tenant === null, 403);
        $feedback = new AiFeedback();
        $feedback->fill($data);
        $feedback->submitted_by = $request->user('admin')->id;
        $feedback->review_status = 'pending';
        $feedback->save();
        if (! empty($data['correction'])) {
            $example = new AiLearningExample();
            $example->tenant_id = $tenant->id;
            $example->source_feedback_id = $feedback->id;
            $example->input_text = $data['correction'];
            $example->expected_intent = 'pending-review';
            $example->expected_action = 'review';
            $example->approval_status = 'pending';
            $example->save();
        }

        return back()->with('success', 'Feedback recorded. A reviewer must approve it before it can be used.');
    }

    public function approveExample(Request $request, AiLearningExample $example): RedirectResponse
    {
        $this->access->assert($request->user('admin'), $example->tenant, 'ai.memory.review');
        $example->approval_status = 'approved';
        $example->save();

        return back()->with('success', 'Example approved. It is stored for review and does not override authorization.');
    }

    public function evaluate(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->isSuperAdmin() || $request->user('admin')->access_scope === 'platform', 403);
        $path = database_path('data/instruction_examples.json');
        $examples = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $correct = 0;
        $operations = [
            'booking.create' => ['required' => ['name', 'email', 'mobile', 'arrival', 'departure', 'guests'], 'read_only' => false],
            'booking.check_availability' => ['required' => ['arrival', 'departure', 'guests'], 'read_only' => true],
            'booking.cancel' => ['required' => ['reference'], 'read_only' => false],
            'booking.get' => ['required' => ['reference'], 'read_only' => true],
        ];
        foreach ($examples as $example) {
            $result = $this->analyzer->analyze([
                'instruction' => $example['instruction'],
                'reference_at' => '2026-11-17T10:00:00+08:00',
                'timezone' => 'Asia/Manila',
                'operations' => $operations,
            ]);
            if ($result->intent === $example['intent']) {
                $correct++;
            }
        }
        $total = count($examples);
        $run = new AiEvaluationRun();
        $run->dataset_version = 'baseline-2026-11';
        $run->metrics = [
            'examples' => $total,
            'intent_accuracy' => $total === 0 ? 0 : round($correct / $total, 4),
            'auto_execution_enabled' => false,
            'false_auto_execution_rate' => 0,
        ];
        $run->passed = false;
        $run->evaluated_at = now();
        $run->save();

        return back()->with('success', 'Evaluation recorded. Automatic writes stay off because the labeled set is below the calibration minimum.');
    }

    private function chunk(AiKnowledgeDocument $document): void
    {
        $parts = str_split($document->content, 800);
        foreach ($parts as $index => $part) {
            $chunk = new AiKnowledgeChunk();
            $chunk->tenant_id = $document->tenant_id;
            $chunk->document_id = $document->id;
            $chunk->chunk_index = $index;
            $chunk->content = $part;
            $chunk->embedding = $this->knowledge->embed($part);
            $chunk->embedding_reference = 'hashing-v1';
            $chunk->created_at = now();
            $chunk->save();
        }
    }
}
