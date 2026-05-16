<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Blog;
use App\Models\CompanyPage;
use App\Models\ContactUs;
use App\Models\NewsletterSubscriber;
use App\Models\SearchQuery;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $stats = [
            'published_articles' => Blog::where('status', 'published')->count(),
            'draft_articles' => Blog::where('status', 'draft')->count(),
            'authors' => Author::count(),
            'company_pages' => CompanyPage::count(),
            'contact_queries' => ContactUs::count(),
            'newsletter_subscribers' => NewsletterSubscriber::count(),
            'search_queries' => SearchQuery::count(),
        ];

        $recentBlogs = Blog::with('author:id,name')->latest()->limit(5)->get();
        $recentContacts = ContactUs::latest()->limit(5)->get();
        $topSearches = SearchQuery::selectRaw('query, COUNT(*) as total')
            ->groupBy('query')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('home', compact('stats', 'recentBlogs', 'recentContacts', 'topSearches'));
    }

    public function changePasswordIndex()
    {
        return view('change_password.index');
    }

    public function changePasswordUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Auth::user();
            if (! Hash::check($request->old_password, Auth::user()->password)) {
                ResponseService::errorResponse('Incorrect old password');
            }
            $user->password = Hash::make($request->confirm_password);
            $user->update();
            ResponseService::successResponse('Password Change Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'HomeController --> changePasswordUpdate');
            ResponseService::errorResponse();
        }
    }

    public function changeProfileIndex()
    {
        return view('change_profile.index');
    }

    public function changeProfileUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.Auth::user()->id,
            'profile' => 'nullable|mimes:jpeg,jpg,png',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Auth::user();
            $data = [
                'name' => $request->name,
                'email' => $request->email,
            ];
            if ($request->hasFile('profile')) {
                $data['profile'] = $request->file('profile')->store('admin_profile', 'public');
            }
            $user->update($data);
            ResponseService::successResponse('Profile Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'HomeController --> updateProfile');
            ResponseService::errorResponse();
        }
    }
}
