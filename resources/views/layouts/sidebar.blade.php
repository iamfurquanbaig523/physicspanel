<div id="sidebar" class="active">
    <div class="sidebar-wrapper active">
        <div class="sidebar-header position-relative">
            <div class="d-block">
                <div class="logo text-center">
                    <a href="{{ url('home') }}">
                        <img src="{{ $company_logo ?? '' }}"
                             data-custom-image="{{ url('assets/images/logo/sidebar_logo.png') }}"
                             alt="Physics Fundamentals">
                    </a>
                </div>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul class="menu">
                <li class="sidebar-item">
                    <a href="{{ url('home') }}" class="sidebar-link">
                        <i class="bi bi-house"></i>
                        <span class="menu-item">{{ __('Dashboard') }}</span>
                    </a>
                </li>

                @canany(['blog-list', 'blog-create', 'blog-update', 'blog-delete', 'category-list', 'category-create', 'category-update', 'category-delete', 'author-list', 'author-create', 'author-update', 'author-delete', 'company-page-list', 'company-page-create', 'company-page-update', 'company-page-delete'])
                    <div class="sidebar-new-title">{{ __('Website Content') }}</div>

                    @canany(['blog-list', 'blog-create', 'blog-update', 'blog-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('blog.index') }}" class="sidebar-link">
                                <i class="bi bi-journal-text"></i>
                                <span class="menu-item">{{ __('Blogs') }}</span>
                            </a>
                        </li>
                    @endcanany

                    @canany(['category-list', 'category-create', 'category-update', 'category-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('category.index') }}" class="sidebar-link">
                                <i class="bi bi-diagram-3"></i>
                                <span class="menu-item">{{ __('Categories') }}</span>
                            </a>
                        </li>
                    @endcanany

                    @canany(['author-list', 'author-create', 'author-update', 'author-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('authors.index') }}" class="sidebar-link">
                                <i class="bi bi-person-lines-fill"></i>
                                <span class="menu-item">{{ __('Authors') }}</span>
                            </a>
                        </li>
                    @endcanany

                    @canany(['company-page-list', 'company-page-create', 'company-page-update', 'company-page-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('company-pages.index') }}" class="sidebar-link">
                                <i class="bi bi-file-earmark-text"></i>
                                <span class="menu-item">{{ __('Company Pages') }}</span>
                            </a>
                        </li>
                    @endcanany
                @endcanany

                @canany(['user-queries-list', 'newsletter-subscriber-list', 'newsletter-subscriber-delete', 'search-query-list', 'search-query-delete'])
                    <div class="sidebar-new-title">{{ __('Audience') }}</div>

                    @canany(['user-queries-list'])
                        <li class="sidebar-item">
                            <a href="{{ route('contact-us.index') }}" class="sidebar-link">
                                <i class="bi bi-envelope"></i>
                                <span class="menu-item">{{ __('Contact Queries') }}</span>
                            </a>
                        </li>
                    @endcanany

                    @canany(['newsletter-subscriber-list', 'newsletter-subscriber-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('newsletter-subscribers.index') }}" class="sidebar-link">
                                <i class="bi bi-at"></i>
                                <span class="menu-item">{{ __('Newsletter Subscribers') }}</span>
                            </a>
                        </li>
                    @endcanany

                    @canany(['search-query-list', 'search-query-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('search-queries.index') }}" class="sidebar-link">
                                <i class="bi bi-search"></i>
                                <span class="menu-item">{{ __('Search Queries') }}</span>
                            </a>
                        </li>
                    @endcanany
                @endcanany

                @canany(['settings-update'])
                    <div class="sidebar-new-title">{{ __('Site Settings') }}</div>

                    @can('settings-update')
                        <li class="sidebar-item">
                            <a href="{{ route('settings.index') }}" class="sidebar-link">
                                <i class="bi bi-gear"></i>
                                <span class="menu-item">{{ __('General Settings') }}</span>
                            </a>
                        </li>
                    @endcan

                    @can('settings-update')
                        <li class="sidebar-item">
                            <a href="{{ route('settings.seo-settings.index') }}" class="sidebar-link">
                                <i class="bi bi-graph-up"></i>
                                <span class="menu-item">{{ __('SEO Settings') }}</span>
                            </a>
                        </li>
                    @endcan
                @endcanany

                @canany(['role-list', 'role-create', 'role-update', 'role-delete', 'staff-list', 'staff-create', 'staff-update', 'staff-delete'])
                    <div class="sidebar-new-title">{{ __('Admin Users') }}</div>

                    @canany(['role-list', 'role-create', 'role-update', 'role-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('roles.index') }}" class="sidebar-link">
                                <i class="bi bi-person-bounding-box"></i>
                                <span class="menu-item">{{ __('Roles') }}</span>
                            </a>
                        </li>
                    @endcanany

                    @canany(['staff-list', 'staff-create', 'staff-update', 'staff-delete'])
                        <li class="sidebar-item">
                            <a href="{{ route('staff.index') }}" class="sidebar-link">
                                <i class="bi bi-person-badge"></i>
                                <span class="menu-item">{{ __('Staff') }}</span>
                            </a>
                        </li>
                    @endcanany
                @endcanany

                @if (\Illuminate\Support\Facades\Auth::user()->hasRole('Super Admin'))
                    <div class="sidebar-new-title">{{ __('Maintenance') }}</div>
                    <li class="sidebar-item">
                        <a href="{{ route('system-update.index') }}" class="sidebar-link">
                            <i class="bi bi-laptop"></i>
                            <span class="menu-item">{{ __('System Update') }}</span>
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>
