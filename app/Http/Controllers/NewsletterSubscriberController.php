<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class NewsletterSubscriberController extends Controller
{
    public function index()
    {
        ResponseService::noAnyPermissionThenRedirect(['newsletter-subscriber-list', 'newsletter-subscriber-delete']);

        return view('newsletter-subscribers.index');
    }

    public function show(Request $request)
    {
        ResponseService::noPermissionThenSendJson('newsletter-subscriber-list');

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = in_array($request->input('sort'), ['id', 'email', 'status', 'source', 'subscribed_at'], true) ? $request->input('sort') : 'id';
        $order = $request->input('order', 'DESC');

        $query = NewsletterSubscriber::search($request->search);
        $total = $query->count();
        $subscribers = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($subscribers as $subscriber) {
            $operate = '';
            if (Auth::user()->can('newsletter-subscriber-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('newsletter-subscribers.destroy', $subscriber->id));
            }

            $rows[] = [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'name' => $subscriber->name,
                'source' => $subscriber->source,
                'status' => ucfirst($subscriber->status),
                'subscribed_at' => $subscriber->subscribed_at ? Carbon::parse($subscriber->subscribed_at)->format('d-m-Y H:i') : '-',
                'operate' => $operate,
            ];
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function destroy(NewsletterSubscriber $newsletterSubscriber)
    {
        ResponseService::noPermissionThenSendJson('newsletter-subscriber-delete');

        try {
            $newsletterSubscriber->delete();
            ResponseService::successResponse('Subscriber deleted successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }
}
