<?php

namespace App\Http\Controllers;

use App\Models\CompanyPage;
use App\Services\BootstrapTableService;
use App\Services\PublicContentCacheService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class CompanyPageController extends Controller
{
    public function index()
    {
        ResponseService::noAnyPermissionThenRedirect(['company-page-list', 'company-page-create', 'company-page-update', 'company-page-delete']);

        return view('company-pages.index');
    }

    public function create()
    {
        ResponseService::noPermissionThenRedirect('company-page-create');

        return view('company-pages.create');
    }

    public function store(Request $request)
    {
        ResponseService::noPermissionThenSendJson('company-page-create');
        $request->validate($this->rules());

        try {
            CompanyPage::create($this->pageData($request));
            PublicContentCacheService::invalidate();

            return redirect(route('company-pages.index'))->with('success', trans('Page Added Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th, 'CompanyPageController->store');

            return ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function show(Request $request)
    {
        ResponseService::noPermissionThenSendJson('company-page-list');

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = in_array($request->input('sort'), ['id', 'title', 'slug', 'page_key', 'status', 'updated_at'], true) ? $request->input('sort') : 'id';
        $order = $request->input('order', 'DESC');

        $query = CompanyPage::search($request->search);
        $total = $query->count();
        $pages = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($pages as $page) {
            $operate = '';
            if (Auth::user()->can('company-page-update')) {
                $operate .= BootstrapTableService::editButton(route('company-pages.edit', $page->id));
            }
            if (Auth::user()->can('company-page-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('company-pages.destroy', $page->id));
            }

            $rows[] = [
                'id' => $page->id,
                'title' => $page->title,
                'page_key' => $page->page_key,
                'slug' => $page->slug,
                'status' => $page->status ? 'Published' : 'Draft',
                'updated_at' => Carbon::parse($page->updated_at)->format('d-m-Y H:i'),
                'operate' => $operate,
            ];
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function edit(CompanyPage $companyPage)
    {
        ResponseService::noPermissionThenRedirect('company-page-update');

        return view('company-pages.edit', compact('companyPage'));
    }

    public function update(Request $request, CompanyPage $companyPage)
    {
        ResponseService::noPermissionThenSendJson('company-page-update');
        $request->validate($this->rules($companyPage->id));

        try {
            $companyPage->update($this->pageData($request, $companyPage->id));
            PublicContentCacheService::invalidate();

            return redirect(route('company-pages.index'))->with('success', trans('Page Updated Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th, 'CompanyPageController->update');

            return ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function destroy(CompanyPage $companyPage)
    {
        ResponseService::noPermissionThenSendJson('company-page-delete');

        try {
            $companyPage->delete();
            PublicContentCacheService::invalidate();
            ResponseService::successResponse('Page deleted successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'page_key' => ['required', 'string', 'max:255', Rule::unique('company_pages', 'page_key')->ignore($ignoreId)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('company_pages', 'slug')->ignore($ignoreId)],
        ];
    }

    private function pageData(Request $request, ?int $ignoreId = null): array
    {
        $data = [
            'page_key' => Str::slug($request->input('page_key')),
            'title' => $request->input('title'),
            'slug' => $this->uniqueSlug($request->input('slug') ?: $request->input('title'), $ignoreId),
            'excerpt' => $request->input('excerpt'),
            'content' => $request->input('content'),
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'published_at' => $request->input('published_at') ?: now(),
        ];

        if ($request->has('status')) {
            $data['status'] = $request->boolean('status');
        } elseif ($ignoreId === null) {
            $data['status'] = true;
        }

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while (CompanyPage::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
