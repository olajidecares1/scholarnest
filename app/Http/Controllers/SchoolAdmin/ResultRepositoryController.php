<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\Examination;
use App\Models\RepositoryResult;
use App\Services\PublishedResultData;
use App\Services\ResultRepository;
use App\Support\AcademicSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Result Repository, as the School Admin sees it.
 *
 * An administrative store, not a portal. Teachers push into it and never see
 * it; pupils and parents read out of it through the result-checking link and
 * never see it either. This controller lives inside the School Admin route
 * group, which is what confines it to them - there is no second check here
 * pretending to do that job.
 *
 * Every query goes through RepositoryResult::forSchool(). A repository holds
 * children's report cards, and one school reading another's would be the worst
 * failure this application could have.
 */
class ResultRepositoryController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(
        private readonly ResultRepository $repository,
        private readonly PublishedResultData $publishedData,
    ) {}

    /**
     * Class, then academic year, then term - the order the brief asks for, and
     * the order a School Admin actually thinks in.
     */
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $options = $this->repository->filterOptions($school);

        $class = $request->filled('class')
            ? $request->string('class')->toString()
            : ($options['classes'][0] ?? null);

        $session = $request->filled('session')
            ? $request->string('session')->toString()
            : ($options['sessions'][0] ?? $school->currentSession());

        $term = ExamTerm::tryFrom($request->string('term')->toString());

        $results = collect();
        $staleIds = [];

        if ($class !== null && $term !== null) {
            $results = RepositoryResult::query()
                ->forSchool($school)
                ->forTerm($class, $session, $term)
                ->with('student')
                ->join('students', 'students.id', '=', 'repository_results.student_id')
                ->orderBy('students.first_name')
                ->orderBy('students.last_name')
                ->select('repository_results.*')
                ->get();

            $staleIds = $this->staleAmong($results, $school->id, $class, $session, $term);
        }

        return view('school-admin.result-repository.index', [
            'classOptions' => $options['classes'],
            'sessionOptions' => $options['sessions'] ?: AcademicSession::options(),
            'termOptions' => ExamTerm::cases(),
            'selectedClass' => $class,
            'selectedSession' => $session,
            'selectedTerm' => $term,
            'results' => $results,
            'staleIds' => $staleIds,
            'totalStored' => RepositoryResult::query()->forSchool($school)->count(),
        ]);
    }

    /**
     * One stored card, rendered from the snapshot rather than from the marks.
     *
     * Deliberately the published document and not a fresh calculation. The
     * School Admin looking here is asking "what did we send out?", and a page
     * that quietly recalculated would answer a different question.
     */
    public function show(Request $request, RepositoryResult $result): JsonResponse
    {
        $this->authorizeSchoolOwnership($result);

        $card = $this->publishedData->rehydrate($result);

        return response()->json([
            'card_number' => $result->payload['student']['admission_number'] ?? null,
            'details_html' => view('school-admin.results._details', $card)->render(),
            'report_card_html' => view('school-admin.results._report-card', $card)->render(),
            'pushed_at' => $result->pushed_at->format('M j, Y g:ia'),
            'pushed_by' => $result->pushed_by_name,
            'version' => $result->version,
        ]);
    }

    /**
     * Which of these have been corrected since they were published.
     *
     * One extra query for the whole page rather than one per row - see
     * ResultRepository::fingerprintsFor().
     *
     * @param  Collection<int, RepositoryResult>  $results
     * @return list<int>
     */
    private function staleAmong(Collection $results, int $schoolId, string $class, string $session, ExamTerm $term): array
    {
        if ($results->isEmpty()) {
            return [];
        }

        // The examination the marks live in now. It can be absent - a school
        // may have deleted it - in which case there is nothing to compare
        // against and nothing is flagged.
        $examination = Examination::query()
            ->where('school_id', $schoolId)
            ->where('class_name', $class)
            ->where('session', $session)
            ->where('term', $term->value)
            ->first();

        if ($examination === null) {
            return [];
        }

        $current = $this->repository->fingerprintsFor($examination, $results->pluck('student_id')->all());

        return $results
            ->filter(fn (RepositoryResult $result) => $result->isStaleAgainst($current[$result->student_id] ?? null))
            ->pluck('id')
            ->all();
    }
}
