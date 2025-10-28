<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AIPersona;
use App\Models\Company;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\Workflow\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowService $workflowService
    ) {
    }

    /**
     * Display workflow list
     */
    public function index(Request $request): View
    {
        $query = Workflow::with(['steps', 'user'])
            ->where('user_id', Auth::id())
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }

        $workflows = $query->paginate(12);

        $stats = [
            'total' => Workflow::where('user_id', Auth::id())->count(),
            'active' => Workflow::where('user_id', Auth::id())->where('status', 'active')->count(),
            'draft' => Workflow::where('user_id', Auth::id())->where('status', 'draft')->count(),
            'archived' => Workflow::where('user_id', Auth::id())->where('status', 'archived')->count(),
        ];

        return view('panel.user.workflows.index', compact('workflows', 'stats'));
    }

    /**
     * Display workflow templates
     */
    public function templates(): View
    {
        $templates = Workflow::with(['steps', 'user'])
            ->where('is_template', true)
            ->where(function ($query) {
                $query->where('visibility', 'public')
                    ->orWhere('user_id', Auth::id());
            })
            ->latest()
            ->paginate(12);

        return view('panel.user.workflows.templates', compact('templates'));
    }

    /**
     * Show create form
     */
    public function create(): View
    {
        // Get user's brands/companies if available
        $brands = Company::where('user_id', Auth::id())->get();

        // Personas feature not available - pass empty collection
        $personas = collect([]);

        return view('panel.user.workflows.create', compact('brands', 'personas'));
    }

    /**
     * Store new workflow
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'status' => 'required|in:active,draft,archived',
            'persona_id' => 'nullable|exists:ai_personas,id',
            'brand_id' => 'nullable|exists:companies,id',
        ]);

        $workflow = $this->workflowService->create($validated, Auth::id());

        return redirect()
            ->route('dashboard.user.workflows.edit', $workflow)
            ->with('success', __('Workflow created successfully'));
    }

    /**
     * Show workflow details
     */
    public function show(Workflow $workflow): View
    {
        if ($workflow->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $workflow->load(['steps', 'executions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $stats = [
            'total_executions' => $workflow->executions()->count(),
            'successful' => $workflow->executions()->where('status', 'completed')->count(),
            'failed' => $workflow->executions()->where('status', 'failed')->count(),
            'avg_duration' => $workflow->executions()
                ->whereNotNull('started_at')
                ->whereNotNull('completed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg')
                ->value('avg'),
        ];

        return view('panel.user.workflows.show', compact('workflow', 'stats'));
    }

    /**
     * Show edit form
     */
    public function edit(Workflow $workflow): View
    {
        if ($workflow->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $workflow->load('steps');
        $personas = AIPersona::where('user_id', Auth::id())->get();
        $brands = Company::where('user_id', Auth::id())->get();

        // Available step types
        $stepTypes = [
            'text' => 'Text Generation',
            'image' => 'Image Generation',
            'video' => 'Video Generation',
            'audio' => 'Audio/TTS',
            'code' => 'Code Generation',
            'decision' => 'Decision/Branch',
            'transform' => 'Data Transformation',
        ];

        return view('panel.user.workflows.edit', compact('workflow', 'personas', 'brands', 'stepTypes'));
    }

    /**
     * Update workflow
     */
    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        if ($workflow->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'status' => 'required|in:active,draft,archived',
            'persona_id' => 'nullable|exists:ai_personas,id',
            'brand_id' => 'nullable|exists:companies,id',
        ]);

        $this->workflowService->update($workflow, $validated);

        return redirect()
            ->route('dashboard.user.workflows.show', $workflow)
            ->with('success', __('Workflow updated successfully'));
    }

    /**
     * Delete workflow
     */
    public function destroy(Workflow $workflow): RedirectResponse
    {
        if ($workflow->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $this->workflowService->delete($workflow);

        return redirect()
            ->route('dashboard.user.workflows.index')
            ->with('success', __('Workflow deleted successfully'));
    }

    /**
     * Execute workflow
     */
    public function execute(Request $request, Workflow $workflow): RedirectResponse
    {
        if ($workflow->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'input_data' => 'required|array',
            'persona_id' => 'nullable|exists:ai_personas,id',
            'brand_id' => 'nullable|exists:companies,id',
        ]);

        $execution = $this->workflowService->execute(
            $workflow,
            $validated['input_data'],
            $validated['persona_id'] ?? null,
            $validated['brand_id'] ?? null
        );

        return redirect()
            ->route('dashboard.user.workflows.execution', $execution)
            ->with('success', __('Workflow execution started'));
    }

    /**
     * Show execution details
     */
    public function execution(WorkflowExecution $execution): View
    {
        if ($execution->workflow->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $execution->load(['workflow', 'stepExecutions.step']);

        return view('panel.user.workflows.execution', compact('execution'));
    }

    /**
     * Cancel execution
     */
    public function cancelExecution(WorkflowExecution $execution): RedirectResponse
    {
        if ($execution->workflow->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $this->workflowService->cancelExecution($execution);

        return redirect()
            ->route('dashboard.user.workflows.execution', $execution)
            ->with('success', __('Workflow execution cancelled'));
    }

    /**
     * Clone workflow
     */
    public function clone(Workflow $workflow): RedirectResponse
    {
        if ($workflow->user_id !== Auth::id() && !$workflow->is_public) {
            abort(403, 'Unauthorized');
        }

        $cloned = $this->workflowService->clone($workflow, Auth::id());

        return redirect()
            ->route('dashboard.user.workflows.edit', $cloned)
            ->with('success', __('Workflow cloned successfully'));
    }
}
