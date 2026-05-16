<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AuthorController extends Controller
{
    private string $uploadFolder = 'authors';

    public function index()
    {
        ResponseService::noAnyPermissionThenRedirect(['author-list', 'author-create', 'author-update', 'author-delete']);

        return view('authors.index');
    }

    public function create()
    {
        ResponseService::noPermissionThenRedirect('author-create');

        return view('authors.create');
    }

    public function store(Request $request)
    {
        ResponseService::noPermissionThenSendJson('author-create');
        $request->validate($this->rules());

        try {
            $data = $request->only(['name', 'role', 'bio', 'email', 'website_url']);
            $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $request->input('name'));
            $data['status'] = $request->boolean('status', true);
            $data['social_links'] = $this->socialLinks($request);

            if ($request->hasFile('avatar')) {
                $data['avatar'] = FileService::compressAndUpload($request->file('avatar'), $this->uploadFolder);
            }

            Author::create($data);

            return redirect(route('authors.index'))->with('success', trans('Author Added Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th, 'AuthorController->store');

            return ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function show(Request $request)
    {
        ResponseService::noPermissionThenSendJson('author-list');

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = in_array($request->input('sort'), ['id', 'name', 'slug', 'role', 'status', 'created_at'], true) ? $request->input('sort') : 'id';
        $order = $request->input('order', 'DESC');

        $query = Author::withCount('blogs')->search($request->search);
        $total = $query->count();
        $authors = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($authors as $author) {
            $operate = '';
            if (Auth::user()->can('author-update')) {
                $operate .= BootstrapTableService::editButton(route('authors.edit', $author->id));
            }
            if (Auth::user()->can('author-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('authors.destroy', $author->id));
            }

            $rows[] = [
                'id' => $author->id,
                'name' => $author->name,
                'slug' => $author->slug,
                'role' => $author->role,
                'email' => $author->email,
                'blogs_count' => $author->blogs_count,
                'status' => $author->status ? 'Active' : 'Inactive',
                'created_at' => Carbon::parse($author->created_at)->format('d-m-Y H:i'),
                'operate' => $operate,
            ];
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function edit(Author $author)
    {
        ResponseService::noPermissionThenRedirect('author-update');

        return view('authors.edit', compact('author'));
    }

    public function update(Request $request, Author $author)
    {
        ResponseService::noPermissionThenSendJson('author-update');
        $request->validate($this->rules($author->id));

        try {
            $data = $request->only(['name', 'role', 'bio', 'email', 'website_url']);
            $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $request->input('name'), $author->id);
            $data['status'] = $request->boolean('status');
            $data['social_links'] = $this->socialLinks($request);

            if ($request->hasFile('avatar')) {
                $data['avatar'] = FileService::compressAndReplace($request->file('avatar'), $this->uploadFolder, $author->getRawOriginal('avatar'));
            }

            $author->update($data);

            return redirect(route('authors.index'))->with('success', trans('Author Updated Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th, 'AuthorController->update');

            return ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function destroy(Author $author)
    {
        ResponseService::noPermissionThenSendJson('author-delete');

        try {
            FileService::delete($author->getRawOriginal('avatar'));
            $author->delete();
            ResponseService::successResponse('Author deleted successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('authors', 'slug')->ignore($ignoreId)],
            'email' => ['nullable', 'email', 'max:255'],
            'avatar' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:7168'],
        ];
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while (Author::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function socialLinks(Request $request): array
    {
        return array_filter([
            'linkedin' => $request->input('linkedin_url'),
            'x' => $request->input('x_url'),
            'github' => $request->input('github_url'),
        ]);
    }
}
