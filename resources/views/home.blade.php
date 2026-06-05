@extends('layouts.main')
@section('title')
    {{ __('Dashboard') }}
@endsection

@section('content')
    <section class="section">
        <div class="dashboard_title mb-3">{{ __('Physics Fundamentals CMS') }}</div>

        <div class="row">
            @foreach([
                ['label' => __('Published Articles'), 'value' => $stats['published_articles'], 'icon' => 'bi bi-journal-text', 'url' => route('blog.index')],
                ['label' => __('Draft Articles'), 'value' => $stats['draft_articles'], 'icon' => 'bi bi-pencil-square', 'url' => route('blog.index')],
                ['label' => __('Authors'), 'value' => $stats['authors'], 'icon' => 'bi bi-person-lines-fill', 'url' => route('authors.index')],
                ['label' => __('Company Pages'), 'value' => $stats['company_pages'], 'icon' => 'bi bi-file-earmark-text', 'url' => route('company-pages.index')],
                ['label' => __('Contact Queries'), 'value' => $stats['contact_queries'], 'icon' => 'bi bi-envelope', 'url' => route('contact-us.index')],
                ['label' => __('Subscribers'), 'value' => $stats['newsletter_subscribers'], 'icon' => 'bi bi-at', 'url' => route('newsletter-subscribers.index')],
                ['label' => __('Search Queries'), 'value' => $stats['search_queries'], 'icon' => 'bi bi-search', 'url' => route('search-queries.index')],
            ] as $card)
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                    <a href="{{ $card['url'] }}">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="total_number">{{ $card['value'] }}</div>
                                    <div class="card_title">{{ $card['label'] }}</div>
                                </div>
                                <i class="{{ $card['icon'] }} fa-2x text-primary"></i>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-lg-7 col-md-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Recent Articles') }}</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Author') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Updated') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($recentBlogs as $blog)
                                    <tr>
                                        <td><a href="{{ route('blog.edit', $blog->id) }}">{{ $blog->title }}</a></td>
                                        <td>{{ $blog->author?->name ?? '-' }}</td>
                                        <td>{{ ucfirst($blog->status ?? 'draft') }}</td>
                                        <td>{{ $blog->updated_at?->format('d-m-Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center">{{ __('No articles yet') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-md-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Recent Contact Queries') }}</h5></div>
                    <div class="card-body">
                        @forelse($recentContacts as $contact)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="fw-bold">{{ $contact->subject }}</div>
                                <div class="small text-muted">{{ $contact->name }} - {{ $contact->email }}</div>
                                <div class="small">{{ \Illuminate\Support\Str::limit($contact->message, 90) }}</div>
                            </div>
                        @empty
                            <div class="text-center text-muted">{{ __('No contact queries yet') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Top Search Queries') }}</h5></div>
                    <div class="card-body">
                        @forelse($topSearches as $query)
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span>{{ $query->query }}</span>
                                <strong>{{ $query->total }}</strong>
                            </div>
                        @empty
                            <div class="text-center text-muted">{{ __('No search queries yet') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
