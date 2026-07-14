<?php

namespace Modules\Credit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Credit\Events\CreditUpdate;
use Modules\Credit\Models\Credit;
use Modules\Purchase\Models\Purchase;
use Modules\Sale\Models\Sale;
use Modules\Status\Models\Status;
use Modules\User\Models\User;
use OpenApi\Attributes as OA;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;
use App\Jobs\ExportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

#[OA\Tag(name: "Credits")]
class CreditController extends Controller
{
    #[OA\Get(
        path: "/credits",
        tags: ["Credits"],
        summary: "List credits",
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "credit_type", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "perPage", in: "query", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Credits fetched successfully"),
        ],
        security: [["bearerAuth" => []]]
    )]
    public function index(Request $request)
    {
        $credits = Credit::with(['user', 'updatedBy'])
            ->when($request->credit_type, function ($query) use ($request) {
                $query->where('credit_type', $request->credit_type);
            })
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('invoice_no', 'like', "%{$request->search}%")
                    ->orWhereHas('user', function ($uq) use ($request) {
                        $uq->where('name', 'like', "%{$request->search}%")
                            ->orWhere('contact', 'like', "%{$request->search}%")
                            ->orWhere('email', 'like', "%{$request->search}%");
                    });
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($request->perPage ?? 15)->onEachSide(0);

        return response()->json([
            'status' => 'success',
            'credits' => $credits,
        ]);
    }

    public function export(Request $request)
    {
        $days = $request->integer('days');

        if ($days) {
            $oldest = Credit::min('created_at');

            if ($oldest) {
                $maxDays = (int) Carbon::parse($oldest)->diffInDays(now());

                if ($days > $maxDays) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Data is only available for the last {$maxDays} day(s). Please enter a value of {$maxDays} or less.",
                    ], 422);
                }
            }
        }

        $credits = Credit::with(['user', 'updatedBy'])
            ->when($request->credit_type, fn($q) => $q->where('credit_type', $request->credit_type))
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('invoice_no', 'like', "%{$request->search}%")
                    ->orWhereHas('user', function ($uq) use ($request) {
                        $uq->where('name', 'like', "%{$request->search}%")
                            ->orWhere('contact', 'like', "%{$request->search}%")
                            ->orWhere('email', 'like', "%{$request->search}%");
                    });
                });
            })
            ->orderBy('id', 'desc')
            ->when($request->ids, fn($q) => $q->whereIn('id', explode(',', $request->ids)))
            ->when($days, fn($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->get();

        $data = $credits->map(fn($c) => [
            'ID' => $c->id,
            'Type' => $c->credit_type ?? '-',
            'Invoice No' => $c->invoice_no ?? '-',
            'Total Amount' => $c->total_amount,
            'Paid Amount' => $c->paid_amount,
            'Due Amount' => $c->due_amount,
            'User' => $c->user?->name ?? '-',
            'Updated By' => $c->updatedBy?->name ?? '-',
            'Created At' => $c->created_at?->format('Y-m-d H:i:s') ?? '-',
            'Updated At' => $c->updated_at?->format('Y-m-d H:i:s') ?? '-',
        ])->toArray();

        $headings = ['ID', 'Type', 'Invoice No', 'Total Amount', 'Paid Amount', 'Due Amount', 'User', 'Updated By', 'Created At', 'Updated At'];
        $filename = 'credits_' . now()->timestamp . '.xlsx';

        ExportData::dispatch($data, $headings, $filename, 'Credits', 'credits');

        return response()->json(['file' => $filename]);
    }

    public function download($filename)
    {
        $path = 'exports/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File is not ready yet.'], 404);
        }
        return response()->download(
            Storage::disk('public')->path($path),
            $filename
        )->deleteFileAfterSend(true);
    }

    public function show(int $id)
    {
        $credit = Credit::with(['user'])
            ->findOrFail($id);

        $sale = null;
        $purchase = null;

        if ($credit->credit_type === 'sale') {
            $sale = Sale::with(['product', 'branch', 'client'])
                ->where('invoice_no', $credit->invoice_no)
                ->first();
        } elseif ($credit->credit_type === 'purchase') {
            $purchase = Purchase::with(['product', 'branch', 'supplier'])
                ->where('invoice_no', $credit->invoice_no)
                ->first();
        }

        return response()->json([
            'status' => 'success',
            'credit' => $credit,
            'sale' => $sale,
            'purchase' => $purchase,
        ]);
    }

    #[OA\Post(
        path: "/credits/update/{id}",
        summary: "Update credit",
        tags: ["Credits"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["paid_amount"],
                properties: [
                    new OA\Property(property: "paid_amount", type: "number", format: "float"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Credit updated"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, int $id)
    {
        $credit = Credit::findOrFail($id);

        $request->validate([
            'paid_amount' => 'required|numeric|min:0|max:9999999999.999',
        ]);

        $newPaid = (float) $request->paid_amount;
        $totalAmount = (float) $credit->total_amount;
        $dueAmount = $totalAmount - $newPaid;

        $credit->updated_by = Auth::id();
        $credit->paid_amount = $newPaid;
        $credit->due_amount = $dueAmount;
        $credit->update();

        if ($credit->credit_type === 'sale') {
            $sales = Sale::where('invoice_no', $credit->invoice_no)->get();
            $saleParent = Status::where('name', 'sale')->first();
            if ($saleParent) {
                $completedStatus = Status::where('name', 'completed')->where('parent_id', $saleParent->id)->first();
                $partialCompletedStatus = Status::where('name', 'partial completed')->where('parent_id', $saleParent->id)->first();

                foreach ($sales as $sale) {
                    if ($dueAmount <= 0 && $completedStatus) {
                        $sale->status_id = $completedStatus->id;
                        $sale->updated_by = Auth::id();
                        $sale->update();
                    } elseif ($dueAmount > 0 && $partialCompletedStatus && $sale->status_id !== $partialCompletedStatus->id) {
                        $sale->status_id = $partialCompletedStatus->id;
                        $sale->updated_by = Auth::id();
                        $sale->update();
                    }
                }
            }
        }

        if ($credit->credit_type === 'purchase') {
            $purchases = Purchase::where('invoice_no', $credit->invoice_no)->get();
            $purchaseParent = Status::where('name', 'purchase')->first();
            if ($purchaseParent) {
                $receivedStatus = Status::where('name', 'received')->where('parent_id', $purchaseParent->id)->first();
                $partialReceivedStatus = Status::where('name', 'partial received')->where('parent_id', $purchaseParent->id)->first();

                foreach ($purchases as $purchase) {
                    if ($dueAmount <= 0 && $receivedStatus) {
                        $purchase->status_id = $receivedStatus->id;
                        $purchase->updated_by = Auth::id();
                        $purchase->update();
                    } elseif ($dueAmount > 0 && $partialReceivedStatus && $purchase->status_id !== $partialReceivedStatus->id) {
                        $purchase->status_id = $partialReceivedStatus->id;
                        $purchase->updated_by = Auth::id();
                        $purchase->update();
                    }
                }
            }
        }

        $credit->load(['user']);

        event(new CreditUpdate($credit));

        return response()->json([
            'status' => 'success',
            'message' => 'Credit updated successfully',
            'credit' => $credit,
        ]);
    }

    #[OA\Post(
        path: "/credits/delete/{id}",
        tags: ["Credits"],
        summary: "Delete credit",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Credit deleted successfully"),
        ],
        security: [["bearerAuth" => []]]
    )]
    public function destroy(int $id)
    {
        $credit = Credit::findOrFail($id);
        $creditData = $credit->toArray();
        $credit->delete();

        event(new CreditUpdate($creditData));

        return response()->json([
            'status' => 'success',
            'message' => 'Credit deleted successfully',
        ]);
    }

    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:credits,id']);
        Credit::whereIn('id', $request->ids)->delete();
        foreach ($request->ids as $id) {
            event(new CreditUpdate(['id' => $id]));
        }
        return response()->json([
            'status' => 'success',
            'message' => count($request->ids) . ' deleted successfully'
        ]);
    }
}
