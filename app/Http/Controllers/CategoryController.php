<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Blog;
use App\Models\CustomField;
use App\Models\CustomFieldCategory;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ArticleShareLinkService;
use App\Services\PublicContentCacheService;
use App\Services\ResponseService;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use function compact;
use function view;

class CategoryController extends Controller {
    private string $uploadFolder;

    public function __construct() {
        $this->uploadFolder = "category";
    }

    public function index() {
        ResponseService::noAnyPermissionThenRedirect(['category-list', 'category-create', 'category-update', 'category-delete']);
        return view('category.index');
    }

    public function create(Request $request) {
        $languages = CachingService::getLanguages()->values();
        ResponseService::noPermissionThenRedirect('category-create');
        $categories = Category::with('subcategories')->get();
        $articles = Blog::orderBy('title')->get();
        $currentArticles = collect();
        return view('category.create', compact('categories', 'languages', 'articles', 'currentArticles'));
    }

    public function store(Request $request) {
        ResponseService::noPermissionThenSendJson('category-create');
        
        $languages = CachingService::getLanguages();
        $defaultLangId = 1;
        $otherLanguages = $languages->where('id', '!=', $defaultLangId);

        $rules = [
            "name.$defaultLangId" => 'required|string|max:120',
            'image'              => 'nullable|mimes:jpg,jpeg,png,webp,avif|max:7168',
            'parent_category_id' => 'nullable|integer',
            "description.$defaultLangId" => 'nullable|string',
            'series_title' => 'nullable|string|max:255',
            'series_description' => 'nullable|string',
            'series_content' => 'nullable|string',
            'icon' => 'nullable|string',
            'accent_color' => 'nullable|string|max:20',
            'show_in_header_nav' => 'nullable|boolean',
            'header_nav_order' => 'nullable|integer|min:0',
            'show_in_mobile_nav' => 'nullable|boolean',
            'mobile_nav_order' => 'nullable|integer|min:0',
            'is_coming_soon' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:512',
            'meta_description' => 'nullable|string',
            'articles' => 'nullable|string',
            'articles_touched' => 'nullable|boolean',
            'slug' => [
                'nullable',
                'regex:/^[a-zA-Z0-9\-_]+$/'
            ],
            'status'             => 'required|boolean',
        ];

        foreach ($otherLanguages as $lang) {
            $langId = $lang->id;
            $rules["name.$langId"] = 'nullable|string|max:120';
            $rules["description.$langId"] = 'nullable|string';
        }

        $request->validate($rules, [
            'slug.regex' => 'Slug must be only English letters, numbers, hyphens (-), or underscores (_).'
        ]);

        try {
            $data = [
                'name' => $request->input("name.$defaultLangId"),
                'description' => $request->input("description.$defaultLangId"),
                'series_title' => $request->input('series_title'),
                'series_description' => $request->input('series_description'),
                'series_content' => $request->input('series_content'),
                'icon' => $request->input('icon'),
                'accent_color' => $request->input('accent_color') ?: '#B8FF35',
                'show_in_header_nav' => $request->boolean('show_in_header_nav'),
                'header_nav_order' => (int) $request->input('header_nav_order', 0),
                'show_in_mobile_nav' => $request->boolean('show_in_mobile_nav'),
                'mobile_nav_order' => (int) $request->input('mobile_nav_order', 0),
                'is_coming_soon' => $request->boolean('is_coming_soon'),
                'meta_title' => $request->input('meta_title'),
                'meta_description' => $request->input('meta_description'),
                'parent_category_id' => $request->parent_category_id,
                'status' => $request->status,
                'is_job_category' => $request->is_job_category ?? 0,
                'price_optional' => $request->price_optional ?? 0,
            ];
            $slug = trim($request->input('slug') ?? '');
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($slug));
            $slug = trim($slug, '-');
            if (empty($slug)) {
                $slug = HelperService::generateRandomSlug();
            }
            $data['slug'] = HelperService::generateUniqueSlug(new Category, $slug);

            if ($request->hasFile('image')) {
                $uploadedImage = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);

                if (! $uploadedImage) {
                    return ResponseService::errorRedirectResponse('Image upload failed. Please try again.');
                }

                $data['image'] = $uploadedImage;
            }

            $category = Category::create($data);
            $this->syncArticles($category, $request->input('articles'), $request->boolean('articles_touched'));

            foreach ($otherLanguages as $lang) {
                $langId = $lang->id;
                $translatedName = $request->input("name.$langId");
                $translatedDescription = $request->input("description.$langId");

                if (!empty($translatedName) || !empty($translatedDescription)) {
                    $category->translations()->create([
                        'name'        => $translatedName ?? '',
                        'description' => $translatedDescription ?? null,
                        'language_id' => $langId,
                    ]);
                }
            }

            app(ArticleShareLinkService::class)->syncAllPublished();
            PublicContentCacheService::invalidate();
            ResponseService::successRedirectResponse("Category Added Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorRedirectResponse();
        }
    }


    public function show(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('category-list');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');
        $sql = Category::without('translations')->withCount('subcategories')->withCount('custom_fields')->withCount('blogs')->with('subcategories');
        if ($id == "0") {
            $sql->whereNull('parent_category_id');
        } else {
            $sql->where('parent_category_id', $id);
        }
        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        if ($sort !== 'advertisements_count') {
            $sql->orderBy($sort, $order);
        }
        $result = $sql->get();


        if ($sort === 'advertisements_count') {
            $result = $result->sortBy(function ($category) {
                return $category->all_items_count;
            }, SORT_REGULAR, strtolower($order) === 'desc')->values();

            $result = $result->slice($offset, $limit)->values();
        } else {
            $result = $result->slice($offset, $limit);
        }
        $total = $sql->count();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($result as $key => $row) {
            $operate = '';
            if (Auth::user()->can('category-update')) {
                $operate .= BootstrapTableService::editButton(route('category.edit', $row->id));
            }

            if (Auth::user()->can('category-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('category.destroy', $row->id));
            }
            if ($row->subcategories_count > 1) {
                $operate .= BootstrapTableService::button('fa fa-list-ol',route('sub.category.order.change', $row->id),['btn-secondary']);
            }
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['subcategories_count'] = $row->subcategories_count . ' ' . __('Subcategories');
            $tempRow['custom_fields_count'] = $row->custom_fields_count . ' ' . __('Custom Fields');
            $tempRow['articles_count'] = $row->blogs_count . ' ' . __('Articles');
            $tempRow['series_title'] = $row->series_title ?: $row->name;
            $tempRow['header_nav'] = $row->show_in_header_nav ? __('Yes') . ' (' . $row->header_nav_order . ')' : __('No');
            $tempRow['mobile_nav'] = $row->show_in_mobile_nav ? __('Yes') . ' (' . $row->mobile_nav_order . ')' : __('No');
            $tempRow['is_coming_soon'] = $row->is_coming_soon ? __('Yes') : __('No');
            $tempRow['operate'] = $operate;
            $tempRow['advertisements_count'] = $row->all_items_count;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id) {
        ResponseService::noPermissionThenRedirect('category-update');
        $category_data = Category::findOrFail($id);
        
        // Initialize translations array with English (default) data
        $translations = [];
        $translations[1] = [
            'name' => $category_data->name,
            'description' => $category_data->description,
        ];
        
        // Add other language translations
        foreach ($category_data->translations as $translation) {
            $translations[$translation->language_id] = [
                'name' => $translation->name,
                'description' => $translation->description,
            ];
        }

        $parent_category_data = Category::find($category_data->parent_category_id);
        $parent_category = $parent_category_data->name ?? '';
        $categories = Category::with('subcategories')->get();
        $articles = Blog::orderBy('title')->get();
        $currentArticles = Blog::where('category_id', $category_data->id)
            ->orderByRaw('CASE WHEN sort_order IS NULL OR sort_order = 0 THEN 999999 ELSE sort_order END ASC')
            ->orderBy('title')
            ->get()
            ->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'sort_order' => $article->sort_order,
            ])
            ->values();
        // Fetch all languages including English
        $languages = CachingService::getLanguages()->values();
        return view('category.edit', compact('category_data', 'parent_category_data','parent_category', 'translations', 'languages','categories', 'articles', 'currentArticles'));
    }

    public function update(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('category-update');
        try {
            $languages = CachingService::getLanguages();
            $defaultLangId = 1;
            $otherLanguages = $languages->where('id', '!=', $defaultLangId);

            $rules = [
                "name.$defaultLangId" => 'required|string|max:120',
                'image'           => 'nullable|mimes:jpg,jpeg,png,webp,avif|max:7168',
                'parent_category_id' => 'nullable|integer',
                "description.$defaultLangId" => 'nullable|string',
                'series_title' => 'nullable|string|max:255',
                'series_description' => 'nullable|string',
                'series_content' => 'nullable|string',
                'icon' => 'nullable|string',
                'accent_color' => 'nullable|string|max:20',
                'show_in_header_nav' => 'nullable|boolean',
                'header_nav_order' => 'nullable|integer|min:0',
                'show_in_mobile_nav' => 'nullable|boolean',
                'mobile_nav_order' => 'nullable|integer|min:0',
                'is_coming_soon' => 'nullable|boolean',
                'meta_title' => 'nullable|string|max:512',
                'meta_description' => 'nullable|string',
                'articles' => 'nullable|string',
                'articles_touched' => 'nullable|boolean',
                'slug' => [
                    'nullable',
                    'regex:/^[a-zA-Z0-9\-_]+$/'
                ],
                'status'          => 'required|boolean',
            ];

            foreach ($otherLanguages as $lang) {
                $langId = $lang->id;
                $rules["name.$langId"] = 'nullable|string|max:120';
                $rules["description.$langId"] = 'nullable|string';
            }

            $request->validate($rules, [
                'slug.regex' => 'Slug must be only English letters, numbers, hyphens (-), or underscores (_).'
            ]);
            
            $category = Category::findOrFail($id);
            if ($request->parent_category_id == $category->id) {
                return back()->withErrors(['parent_category' => 'A category cannot be set as its own parent.']);
            }
            
            $data = [
                'name' => $request->input("name.$defaultLangId"),
                'description' => $request->input("description.$defaultLangId"),
                'series_title' => $request->input('series_title'),
                'series_description' => $request->input('series_description'),
                'series_content' => $request->input('series_content'),
                'icon' => $request->input('icon'),
                'accent_color' => $request->input('accent_color') ?: '#B8FF35',
                'meta_title' => $request->input('meta_title'),
                'meta_description' => $request->input('meta_description'),
                'parent_category_id' => $request->parent_category_id,
                'status' => $request->status,
                'is_job_category' => $request->is_job_category ?? 0,
                'price_optional' => $request->price_optional ?? 0,
            ];

            if ($request->has('show_in_header_nav')) {
                $data['show_in_header_nav'] = $request->boolean('show_in_header_nav');
            }
            if ($request->has('header_nav_order')) {
                $data['header_nav_order'] = (int) $request->input('header_nav_order', 0);
            }
            if ($request->has('show_in_mobile_nav')) {
                $data['show_in_mobile_nav'] = $request->boolean('show_in_mobile_nav');
            }
            if ($request->has('mobile_nav_order')) {
                $data['mobile_nav_order'] = (int) $request->input('mobile_nav_order', 0);
            }
            if ($request->has('is_coming_soon')) {
                $data['is_coming_soon'] = $request->boolean('is_coming_soon');
            }

            if ($request->hasFile('image')) {
                $uploadedImage = FileService::compressAndReplace($request->file('image'), $this->uploadFolder, $category->getRawOriginal('image'));

                if (! $uploadedImage) {
                    return ResponseService::errorRedirectResponse('Image upload failed. Please try again.');
                }

                $data['image'] = $uploadedImage;
            }
            $slug = trim($request->input('slug') ?? '');
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($slug));
            $slug = trim($slug, '-');
            if (empty($slug)) {
                $slug = HelperService::generateRandomSlug();
            }
            $data['slug'] = HelperService::generateUniqueSlug(new Category(), $slug, $category->id);
            $category->update($data);
            $this->syncArticles($category, $request->input('articles'), $request->boolean('articles_touched'));
            
            if ($request->has('is_job_category')) {
                $category->subcategories()->update([
                    'is_job_category' => $request->is_job_category ? 1 : 0,
                ]);
            }
            
            if ($request->has('price_optional')) {
                $category->subcategories()->update([
                    'price_optional' => $request->price_optional ? 1 : 0,
                ]);
            }
            
            foreach ($otherLanguages as $lang) {
                $langId = $lang->id;
                $translatedName = $request->input("name.$langId");
                $translatedDescription = $request->input("description.$langId");

                CategoryTranslation::updateOrCreate(
                    ['category_id' => $category->id, 'language_id' => $langId],
                    [
                        'name' => $translatedName ?? '',
                        'description' => $translatedDescription ?? null
                    ]
                );
            }

            app(ArticleShareLinkService::class)->syncAllPublished();
            PublicContentCacheService::invalidate();
            ResponseService::successRedirectResponse("Category Updated Successfully", route('category.index'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    // public function destroy($id) {
    //     ResponseService::noPermissionThenSendJson('category-delete');
    //     try {
    //        $category = Category::withCount(['subcategories', 'custom_fields'])
    //         ->with('subcategories')
    //         ->findOrFail($id);
    //         if ($category->all_items_count > 0) {
    //             ResponseService::errorResponse('Cannot delete category. It has associated advertisements.');
    //         }
    //        if ($category->other_items_count > 0) {
    //             ResponseService::errorResponse(
    //                 'Cannot delete category. Delete non-active items first.'
    //             );
    //         }
    //         if ($category->subcategories_count > 0 || $category->custom_fields_count > 0) {
    //             ResponseService::errorResponse('Failed to delete category', 'Cannot delete category. Remove associated subcategories and custom fields first.');
    //         }
    //         if ($category->delete()) {
    //             ResponseService::successResponse('Category delete successfully');
    //         }
    //     } catch (QueryException $th) {
    //         ResponseService::logErrorResponse($th, 'Failed to delete category', 'Cannot delete category. Remove associated subcategories and custom fields first.');
    //         ResponseService::errorResponse('Something Went Wrong');
    //     } catch (Throwable $th) {
    //         ResponseService::logErrorResponse($th, "CategoryController -> delete");
    //         ResponseService::errorResponse('Something Went Wrong');
    //     }
    // }

    public function destroy($id)
{
    ResponseService::noPermissionThenSendJson('category-delete');

    try {
        $category = Category::withCount([
            'subcategories',
            'custom_fields',
            'blogs',
            'items as all_items_count',
            'items as other_items_count' => function ($q) {
                $q->where('status', '!=', 'active');
            }
        ])->findOrFail($id);

        if ($category->all_items_count > 0) {
            return ResponseService::errorResponse(
                'Cannot delete category. It has associated advertisements.'
            );
        }

        if ($category->other_items_count > 0) {
            return ResponseService::errorResponse(
                'Cannot delete category. Delete non-active items first.'
            );
        }

        if ($category->blogs_count > 0) {
            return ResponseService::errorResponse(
                'Cannot delete category. Remove associated blog articles first.'
            );
        }

        if ($category->subcategories_count > 0 || $category->custom_fields_count > 0) {
            return ResponseService::errorResponse(
                'Cannot delete category. Remove associated subcategories and custom fields first.'
            );
        }

        $category->delete();
        PublicContentCacheService::invalidate();

        return ResponseService::successResponse('Category deleted successfully');

    } catch (Throwable $th) {
        ResponseService::logErrorResponse($th, 'CategoryController -> destroy');
        return ResponseService::errorResponse('Something went wrong');
    }
}


    public function getSubCategories($id) {
        ResponseService::noPermissionThenRedirect('category-list');
        $subcategories = Category::where('parent_category_id', $id)
            ->with('subcategories')
            ->withCount('custom_fields')
            ->withCount('subcategories')
            ->withCount('items')
            ->orderBy('sequence')
            ->get()
            ->map(function ($subcategory) {
                $operate = '';
                if (Auth::user()->can('category-update')) {
                    $operate .= BootstrapTableService::editButton(route('category.edit', $subcategory->id));
                }
                if (Auth::user()->can('category-delete')) {
                    $operate .= BootstrapTableService::deleteButton(route('category.destroy', $subcategory->id));
                }
                if ($subcategory->subcategories_count > 1) {
                    $operate .= BootstrapTableService::button('fa fa-list-ol',route('sub.category.order.change',$subcategory->id),['btn-secondary']);
                }
                $subcategory->operate = $operate;
                return $subcategory;
            });

        return response()->json($subcategories);
    }

    public function customFields($id) {
        ResponseService::noPermissionThenRedirect('custom-field-list');
        $category = Category::find($id);
        $p_id = $category->parent_category_id;
        $cat_id = $category->id;
        $category_name = $category->name;

        return view('category.custom-fields', compact('cat_id', 'category_name', 'p_id'));
    }

    public function getCategoryCustomFields(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('custom-field-list');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $sql = CustomField::whereHas('categories', static function ($q) use ($id) {
            $q->where('category_id', $id);
        })->orderBy($sort, $order);

        if (isset($request->search)) {
            $sql->search($request->search);
        }

        $sql->take($limit);
        $total = $sql->count();
        $res = $sql->skip($offset)->take($limit)->get();
        $bulkData = array();
        $rows = array();
        $tempRow['type'] = '';


        foreach ($res as $row) {
            $tempRow = $row->toArray();
//            $operate = BootstrapTableService::editButton(route('custom-fields.edit', $row->id));
            $operate = BootstrapTableService::deleteButton(route('category.custom-fields.destroy', [$id, $row->id]));
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        $bulkData['total'] = $total;
        return response()->json($bulkData);
    }

    public function destroyCategoryCustomField($categoryID, $customFieldID) {
        try {
            ResponseService::noPermissionThenRedirect('custom-field-delete');
            CustomFieldCategory::where(['category_id' => $categoryID, 'custom_field_id' => $customFieldID])->delete();
            ResponseService::successResponse("Custom Field Deleted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "CategoryController -> destroyCategoryCustomField");
            ResponseService::errorResponse('Something Went Wrong');
        }

    }

    public function categoriesReOrder(Request $request) {
        $categories = Category::whereNull('parent_category_id')->orderBy('sequence')->get();
        return view('category.categories-order', compact('categories'));
    }

    public function subCategoriesReOrder(Request $request ,$id) {
        $categories = Category::with('subcategories')->where('parent_category_id', $id)->orderBy('sequence')->get();
        return view('category.sub-categories-order', compact('categories'));
    }

    public function updateOrder(Request $request) {
            $request->validate([
            'order' => 'required'
            ]);
        try {
       $order = json_decode($request->input('order'), true);
        $data = [];
        foreach ($order as $index => $id) {
            $data[] = [
                'id' => $id,
                'sequence' => $index + 1,
            ];
        }
        Category::upsert($data, ['id'], ['sequence']);
        PublicContentCacheService::invalidate();
        ResponseService::successResponse("Order Updated Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    private function syncArticles(Category $category, ?string $articlesInput, bool $articlesTouched = true): void
    {
        if (! $articlesTouched || $articlesInput === null) {
            return;
        }

        $articles = json_decode($articlesInput, true);
        if (! is_array($articles)) {
            return;
        }

        $articleIds = collect($articles)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        Blog::where('category_id', $category->id)
            ->whereNotIn('id', $articleIds)
            ->update([
                'category_id' => null,
                'category' => null,
                'sort_order' => 0,
            ]);

        foreach ($articles as $index => $article) {
            if (empty($article['id'])) {
                continue;
            }

            Blog::where('id', (int) $article['id'])->update([
                'category_id' => $category->id,
                'category' => $category->name,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
