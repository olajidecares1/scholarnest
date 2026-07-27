<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CbtDocumentExtractionService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    private const TOOL_NAME = 'record_extracted_exam';

    /**
     * Extract structured exam data directly from a PDF (Claude reads the PDF natively).
     *
     * @return array{exam_body: string, subject: string, years: list<array{year: int, questions: list<array<string, mixed>>}>}
     */
    public function extractFromPdf(string $absolutePath, ?string $knownExamBody, ?string $knownSubject): array
    {
        $base64 = base64_encode((string) file_get_contents($absolutePath));

        return $this->call([
            [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => $base64,
                ],
            ],
            ['type' => 'text', 'text' => $this->instructions($knownExamBody, $knownSubject)],
        ]);
    }

    /**
     * Extract structured exam data from plain text already pulled out of a .docx file.
     *
     * @return array{exam_body: string, subject: string, years: list<array{year: int, questions: list<array<string, mixed>>}>}
     */
    public function extractFromText(string $text, ?string $knownExamBody, ?string $knownSubject): array
    {
        return $this->call([
            ['type' => 'text', 'text' => "Document text:\n\n".$text],
            ['type' => 'text', 'text' => $this->instructions($knownExamBody, $knownSubject)],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $contentBlocks
     * @return array{exam_body: string, subject: string, years: list<array{year: int, questions: list<array<string, mixed>>}>}
     */
    private function call(array $contentBlocks): array
    {
        $apiKey = config('services.anthropic.key');

        if (! $apiKey) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ])
            ->timeout(600)
            ->post(self::API_URL, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 32000,
                'tools' => [$this->toolSchema()],
                'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
                'messages' => [
                    ['role' => 'user', 'content' => $contentBlocks],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API request failed: '.$response->body());
        }

        $payload = $response->json();

        if (($payload['stop_reason'] ?? null) === 'max_tokens') {
            throw new RuntimeException('The document produced more output than the model could return in one pass. Try splitting it into smaller uploads (e.g. by year range).');
        }

        $toolUse = collect($payload['content'] ?? [])->firstWhere('type', 'tool_use');

        if (! $toolUse) {
            throw new RuntimeException('The model did not return structured exam data.');
        }

        return $toolUse['input'];
    }

    private function instructions(?string $knownExamBody, ?string $knownSubject): string
    {
        $lines = [
            'You are extracting official past examination questions from a document for a Nigerian school\'s computer-based testing system.',
            'Identify the examination body (e.g. WAEC, NECO, JAMB, BECE) and the subject, unless already given below.',
            'If the document spans multiple examination years, group the questions correctly under each distinct year.',
            'For every question, preserve the exact original wording, numbering, and option text as printed - including mathematical notation, using standard characters (e.g. root signs, pi, superscripts, fractions written as "a/b").',
            'If a question references a diagram, chart, graph, table, or labelled illustration, set has_diagram to true and briefly describe it and where it appears in diagram_description. Do not invent a description if there is none.',
            'Only set correct_label when there is an explicit answer key, marking scheme, or clearly indicated correct answer in THIS document itself (e.g. a printed answer key page, "Ans: B", a bolded/underlined option). Never solve or guess the answer yourself. If no such key exists for a question, set correct_label to null and answer_source to "not_found".',
            'Call the '.self::TOOL_NAME.' tool exactly once with the complete result.',
        ];

        if ($knownExamBody) {
            $lines[] = "The examination body is already known to be: {$knownExamBody}. Use this exact value.";
        }

        if ($knownSubject) {
            $lines[] = "The subject is already known to be: {$knownSubject}. Use this exact value.";
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function toolSchema(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Record the exam metadata and every question extracted from the document.',
            'input_schema' => [
                'type' => 'object',
                'required' => ['exam_body', 'subject', 'years'],
                'properties' => [
                    'exam_body' => ['type' => 'string'],
                    'subject' => ['type' => 'string'],
                    'years' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['year', 'questions'],
                            'properties' => [
                                'year' => ['type' => 'integer'],
                                'questions' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'required' => ['number', 'question_text', 'options', 'has_diagram', 'answer_source'],
                                        'properties' => [
                                            'number' => ['type' => 'integer'],
                                            'question_text' => ['type' => 'string'],
                                            'has_diagram' => ['type' => 'boolean'],
                                            'diagram_description' => ['type' => ['string', 'null']],
                                            'options' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'required' => ['label', 'text'],
                                                    'properties' => [
                                                        'label' => ['type' => 'string'],
                                                        'text' => ['type' => 'string'],
                                                    ],
                                                ],
                                            ],
                                            'correct_label' => ['type' => ['string', 'null']],
                                            'answer_source' => [
                                                'type' => 'string',
                                                'enum' => ['found_in_document', 'not_found'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
