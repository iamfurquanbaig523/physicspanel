<?php

namespace App\Http\Controllers;

use App\Models\SearchQuery;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SearchQueryController extends Controller
{
    public function index()
    {
        ResponseService::noAnyPermissionThenRedirect(['search-query-list', 'search-query-delete']);

        return view('search-queries.index');
    }

    public function show(Request $request)
    {
        ResponseService::noPermissionThenSendJson('search-query-list');

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = in_array($request->input('sort'), ['id', 'query', 'page', 'source', 'results_count', 'created_at'], true) ? $request->input('sort') : 'id';
        $order = $request->input('order', 'DESC');

        $query = SearchQuery::search($request->search);
        $total = $query->count();
        $queries = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($queries as $searchQuery) {
            $operate = '';
            if (Auth::user()->can('search-query-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('search-queries.destroy', $searchQuery->id));
            }

            $rows[] = [
                'id' => $searchQuery->id,
                'query' => $searchQuery->query,
                'page' => $searchQuery->page,
                'source' => $searchQuery->source,
                'results_count' => $searchQuery->results_count,
                'created_at' => Carbon::parse($searchQuery->created_at)->format('d-m-Y H:i'),
                'operate' => $operate,
            ];
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function destroy(SearchQuery $searchQuery)
    {
        ResponseService::noPermissionThenSendJson('search-query-delete');

        try {
            $searchQuery->delete();
            ResponseService::successResponse('Search query deleted successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }
}
