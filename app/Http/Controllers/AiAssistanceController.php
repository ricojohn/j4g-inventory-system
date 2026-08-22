<?php

namespace App\Http\Controllers;

use App\Exceptions\IntegrationException;
use App\Http\Requests\AskAiAssistanceRequest;
use App\Http\Requests\ExportAiAssistanceRequest;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Assistance\AiAssistanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAssistanceController extends Controller
{
    public function __construct(
        private AiAssistanceService $aiAssistanceService,
        private AiProviderManager $aiProviderManager,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('use ai assistance'), 403);

        $this->aiProviderManager->ensureProviderRows();
        $providers = $this->aiProviderManager->listProviders();
        $defaultProviderKey = $this->aiProviderManager->getDefaultProviderKey();
        $currentProvider = collect($providers)->firstWhere('provider', $defaultProviderKey);
        $connected = filled($defaultProviderKey);

        return view('ai.assistance.index', [
            'connected' => $connected,
            'currentProvider' => $currentProvider,
            'canManageIntegrations' => $request->user()->can('manage integrations'),
            'assistanceConfig' => [
                'connected' => $connected,
                'urls' => [
                    'ask' => route('ai.assistance.ask'),
                    'exportCsv' => route('ai.assistance.export.csv'),
                    'exportPdf' => route('ai.assistance.export.pdf'),
                ],
                'suggestions' => [
                    'Give me a low stock summary for active products.',
                    'Summarize customer orders this month by status.',
                    'Provide a finance overview for the last 30 days.',
                    'Show the current production pipeline by stage.',
                ],
            ],
        ]);
    }

    public function ask(AskAiAssistanceRequest $request): JsonResponse
    {
        try {
            $result = $this->aiAssistanceService->ask(
                $request->string('message')->toString(),
                $request->input('history', []),
            );

            return response()->json([
                'success' => true,
                'answer' => $result['answer'],
                'tool_trace' => $result['tool_trace'],
                'rows' => $result['rows'],
            ]);
        } catch (IntegrationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function exportCsv(ExportAiAssistanceRequest $request): StreamedResponse
    {
        $answer = $request->string('answer')->toString();
        $rows = $request->input('rows', []);
        $title = $request->input('title') ?: 'AI Assistance Report';
        $filename = 'ai-assistance-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($answer, $rows, $title): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['title', $title]);
            fputcsv($handle, ['generated_at', now()->toDateTimeString()]);
            fputcsv($handle, []);

            if (is_array($rows) && $rows !== [] && is_array($rows[0] ?? null)) {
                $headers = array_keys($rows[0]);
                fputcsv($handle, $headers);

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    fputcsv($handle, array_map(
                        fn ($header) => $this->csvCell($row[$header] ?? ''),
                        $headers
                    ));
                }

                fputcsv($handle, []);
            }

            fputcsv($handle, ['summary']);
            fputcsv($handle, [$answer]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(ExportAiAssistanceRequest $request): Response
    {
        $answer = $request->string('answer')->toString();
        $rows = $request->input('rows', []);
        $title = $request->input('title') ?: 'AI Assistance Report';

        if (! is_array($rows)) {
            $rows = [];
        }

        $headers = [];

        if ($rows !== [] && is_array($rows[0] ?? null)) {
            $headers = array_keys($rows[0]);
        }

        $pdf = Pdf::loadView('ai.assistance.export-pdf', [
            'title' => $title,
            'generatedAt' => now()->format('M d, Y H:i'),
            'answerHtml' => nl2br(e($answer)),
            'headers' => $headers,
            'rows' => $rows,
        ]);

        $filename = 'ai-assistance-'.now()->format('Ymd-His').'.pdf';

        return $pdf->download($filename);
    }

    private function csvCell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return Str::of((string) $value)->replace(["\r\n", "\n", "\r"], ' ')->toString();
    }
}
