<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class HolidayController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page  = (int) request('page', 1);
            $limit = (int) request('limit', 10);
            $search = (string) request('search', '');

            $query = Holiday::query()
                ->where('status', true)
                ->orderByDesc('date');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            $total = (clone $query)->count();
            $rows  = $query->skip(($page - 1) * $limit)->take($limit)->get();

            return $this->sendPaginateResponse(
                'Fetch all holidays',
                $page,
                $limit,
                $total,
                $rows
            );
        } catch (\Throwable $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $holiday = Holiday::find($id);
            if (! $holiday) {
                return $this->sendErrorOfNotFound404('Holiday not found');
            }

            return $this->sendSuccessResponse('Fetch one holiday', $holiday);
        } catch (\Throwable $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), $this->rules());
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $dateStr = Carbon::parse($request->date)->toDateString();
            $existing = Holiday::whereDate('date', $dateStr)->where('status', true)->first();
            if ($existing) {
                return $this->sendErrorOfBadResponse('Date already exists');
            }

            $holiday = Holiday::create([
                'title'         => $request->title,
                'description'   => $request->description,
                'date'          => $dateStr,
                'status'        => $request->boolean('status', true),
                'created_by_id' => Auth::id(),
                'updated_by_id' => Auth::id(),
            ]);

            DB::commit();
            return $this->sendSuccessResponse('Holiday created successfully', $holiday);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $holiday = Holiday::find($id);
            if (! $holiday) {
                return $this->sendErrorOfNotFound404('Holiday not found');
            }

            $validator = Validator::make($request->all(), $this->rules($holiday->id));
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            if ($request->has('date')) {
                $dateStr = Carbon::parse($request->date)->toDateString();
                $conflict = Holiday::whereDate('date', $dateStr)
                    ->where('status', true)
                    ->where('id', '!=', $holiday->id)
                    ->first();
                if ($conflict) {
                    return $this->sendErrorOfBadResponse('Date already exists');
                }
            }

            $holiday->fill([
                'title'         => $request->has('title')       ? $request->title       : $holiday->title,
                'description'   => $request->has('description') ? $request->description : $holiday->description,
                'date'          => $request->has('date')        ? Carbon::parse($request->date)->toDateString() : $holiday->date,
                'status'        => $request->has('status')      ? $request->boolean('status') : $holiday->status,
                'updated_by_id' => Auth::id(),
            ])->save();

            DB::commit();
            return $this->sendSuccessResponse('Holiday updated successfully', $holiday);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $holiday = Holiday::find($id);
            if (! $holiday) {
                return $this->sendErrorOfNotFound404('Holiday not found');
            }

            DB::beginTransaction();

            $holiday->update([
                'status'        => false,
                'updated_by_id' => Auth::id(),
            ]);

            DB::commit();
            return $this->sendSuccessResponse('Holiday deleted successfully', $holiday);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function bulkImport(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => ['required', 'file', 'mimes:xlsx,xls'],
            ]);
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $path = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheet(0);
            $rows  = $sheet->toArray(null, true, true, true); // header row + data

            if (count($rows) < 2) {
                return $this->sendErrorOfBadResponse('No data found in the sheet');
            }

            $headerRow = array_shift($rows);
            $headers = [];
            foreach ($headerRow as $col => $val) {
                $headers[] = is_string($val) ? trim(mb_strtolower($val)) : '';
            }
            $required = ['title', 'description', 'date'];
            $missing  = array_diff($required, $headers);
            if (! empty($missing)) {
                return $this->sendErrorOfBadResponse('Missing required headers: ' . implode(', ', $missing));
            }

            $lettersToNames = [];
            $i = 0;
            foreach ($headerRow as $letter => $val) {
                $name = $headers[$i] ?? '';
                if ($name !== '') {
                    $lettersToNames[$letter] = $name;
                }
                $i++;
            }

            $formatted = [];
            foreach ($rows as $row) {
                if (! is_array($row)) continue;

                $mapped = [];
                foreach ($row as $letter => $value) {
                    if (! array_key_exists($letter, $lettersToNames)) continue;
                    $key = $lettersToNames[$letter];
                    $mapped[$key] = $value ?? '';
                }

                $title = isset($mapped['title']) ? trim((string) $mapped['title']) : '';
                $description = isset($mapped['description']) ? trim((string) $mapped['description']) : '';
                $dateCell = $mapped['date'] ?? '';

                if ($title === '' && $description === '' && $dateCell === '') {
                    continue;
                }

                $dateStr = $this->normalizeExcelDateToDateString($dateCell);
                if ($dateStr === '') {
                    continue;
                }

                $formatted[] = [
                    'title'         => $title,
                    'description'   => $description,
                    'date'          => $dateStr,
                    'status'        => true,
                    'created_by_id' => Auth::id(),
                    'updated_by_id' => Auth::id(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            if (empty($formatted)) {
                return $this->sendSuccessResponse('No rows to import', [
                    'total_rows'       => 0,
                    'total_success'    => 0,
                    'total_duplicates' => 0,
                    'total_errors'     => 0,
                ]);
            }

            $allDates = collect($formatted)->pluck('date')->filter()->unique()->values()->all();
            $existing = Holiday::query()
                ->where('status', true)
                ->whereIn('date', $allDates)
                ->get(['date']);
            $existingSet = array_flip($existing->map(fn($h) => Carbon::parse($h->date)->toDateString())->toArray());

            $toInsert = array_values(array_filter($formatted, fn($r) => ! isset($existingSet[$r['date']])));
            $toInsert = array_map(function ($row) {
                $row['id'] = (string) Str::uuid();
                return $row;
            }, $toInsert);

            if (!empty($toInsert)) {
                Holiday::insert($toInsert);
            }

            $totalRows   = count($formatted);
            $totalInsert = count($toInsert);
            $totalDupes  = $totalRows - $totalInsert;

            return $this->sendSuccessResponse('Holiday imported successfully', [
                'total_rows'       => $totalRows,
                'total_success'    => $totalInsert,
                'total_duplicates' => $totalDupes,
                'total_errors'     => 0,
            ]);
        } catch (\Throwable $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    private function rules($id = null): array
    {
        if ($id) {
            return [
                'title'       => 'required|string|max:100',
                'description' => 'required|string|max:255',
                'date'        => 'required|date',
                'status'      => 'required|boolean',
            ];
        } else {
            return [
                'title'       => 'required|string|max:100',
                'description' => 'required|string|max:255',
                'date'        => 'required|date',
                'status'      => 'required|boolean',
            ];
        }
    }

    private function normalizeExcelDateToDateString($cell): string
    {
        if (is_numeric($cell)) {
            $base = Carbon::create(1899, 12, 30);
            return $base->copy()->addDays((int) $cell)->toDateString();
        }
        try {
            return Carbon::parse((string) $cell)->toDateString();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
