@extends('layouts.main')

@section('title')
    {{__("Bulk Update Custom Fields")}}
@endsection

 @section('page-title')
    <div class="page-title">
        <div class="row d-flex align-items-center">
            <div class="col-12 col-md-6">
                <h4 class="mb-0">@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 text-end">
                <a href="{{ route('custom-fields.index') }}" class="btn btn-secondary mb-0">
                    <i class="fas fa-arrow-left"></i> {{__("Back")}}
                </a>
            </div>
        </div>
    </div>
@endsection
@section('content')
    <section class="section">
        <div class="row">
            <div class="col-md-12 mb-2">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-info-circle me-2"></i> {{__("Instructions & Reference Guide")}}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            {{-- Section 1: Important Instructions --}}
                            <div class="col-md-4">
                                <div class="h-100 p-3 border rounded">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <h6 class="mb-0 ms-3 fw-bold">{{__("Important Instructions")}}</h6>
                                    </div>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <span>{{__("Download current custom fields to get the existing data")}}</span>
                                        </li>
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <span>{{__("Do NOT modify the ID column - it's required for updates")}}</span>
                                        </li>
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <span>{{__("Edit the fields you want to update in the downloaded file")}}</span>
                                        </li>
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <span>{{__("For image paths, use the gallery section to upload new images")}}</span>
                                        </li>
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <span>{{__("Category IDs should be comma-separated if multiple categories")}}</span>
                                        </li>
                                        <li class="mb-0 d-flex align-items-start">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <span>{{__("Values for radio, dropdown, and checkbox types should be pipe-separated (|)")}}</span>
                                        </li>
                                        <li class="mb-0 d-flex align-items-start mt-3">
                                            <i class="fas fa-info-circle text-info me-2 mt-1"></i>
                                            <span><strong>{{__("Note:")}}</strong> {{__("You can open the CSV file in Excel, edit it, and save it. Excel will maintain the CSV format.")}}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-4">
                            <div class="h-100 p-3 border rounded">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <h6 class="mb-0 ms-3 fw-bold">{{__("Quick Reference")}}</h6>
                                </div>

                                <div class="mb-4">
                                    <strong class="d-block mb-2">{{__("Field Types:")}}</strong>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-primary">Number</span>
                                        <span class="badge bg-secondary">Textbox</span>
                                        <span class="badge bg-info text-dark">Fileinput</span>
                                        <span class="badge bg-warning text-dark">Radio</span>
                                        <span class="badge bg-success">Dropdown</span>
                                        <span class="badge bg-danger">Checkbox</span>
                                    </div>
                                </div>

                                <div>
                                    <strong class="d-block mb-2">{{__("Data Format:")}}</strong>
                                    <ul class="list-unstyled mb-0 small">
                                        <li class="mb-2">
                                            <i class="fas fa-dot-circle text-primary me-2"></i>
                                            <strong>{{__("Required:")}}</strong> 0 = {{__("Optional")}}, 1 = {{__("Required")}}
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-dot-circle text-primary me-2"></i>
                                            <strong>{{__("Status:")}}</strong> 0 = {{__("Inactive")}}, 1 = {{__("Active")}}
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-dot-circle text-primary me-2"></i>
                                            <strong>{{__("Values:")}}</strong> {{__("Use pipe (|) to separate options")}}
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-dot-circle text-primary me-2"></i>
                                            <strong>{{__("Categories:")}}</strong> {{__("Use comma (,) to separate IDs")}}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>


                            {{-- Section 3: Gallery Instructions --}}
                            <div class="col-md-4">
                                <div class="h-100 p-3 border rounded">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-images"></i>
                                        </div>
                                        <h6 class="mb-0 ms-3 fw-bold">{{__("Image Gallery Guide")}}</h6>
                                    </div>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-arrow-right text-info me-2 mt-1"></i>
                                            <span>{{__("Click 'Open Image Gallery' button to upload images")}}</span>
                                        </li>
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-arrow-right text-info me-2 mt-1"></i>
                                            <span>{{__("Upload multiple images at once (JPG, PNG, SVG)")}}</span>
                                        </li>
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-arrow-right text-info me-2 mt-1"></i>
                                            <span>{{__("Click on image or 'Copy Path' button to copy image path")}}</span>
                                        </li>
                                        <li class="mb-3 d-flex align-items-start">
                                            <i class="fas fa-arrow-right text-info me-2 mt-1"></i>
                                            <span>{{__("Paste the copied path in CSV file's Image column")}}</span>
                                        </li>
                                        <li class="mb-0 d-flex align-items-start">
                                            <i class="fas fa-arrow-right text-info me-2 mt-1"></i>
                                            <span><strong>{{__("Format:")}}</strong> custom-fields/filename.jpg</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-download me-2"></i> {{__("Download Current Custom Fields & Image Gallery")}}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-file-download text-warning me-2 fs-5"></i>
                                        <h6 class="mb-0 fw-bold">{{__("Download Current Data")}}</h6>
                                    </div>
                                    <p class="text-muted small mb-3">{{__("Download all existing custom fields to edit and update them")}}</p>
                                    <div class="d-grid gap-2">
                                        <a href="{{ route('custom-fields.bulk-update.download') }}" class="btn btn-warning">
                                            <i class="fas fa-file-csv me-2"></i> <span class="d-none d-sm-inline">{{__("Download Current Custom Fields")}}</span><span class="d-sm-none">{{__("Download CSV")}}</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-images text-info me-2 fs-5"></i>
                                        <h6 class="mb-0 fw-bold">{{__("Image Gallery")}}</h6>
                                    </div>
                                    <p class="text-muted small mb-3">{{__("Upload images and copy their paths to use in your CSV file. The gallery allows you to manage all custom field images in one place.")}}</p>
                                    <button type="button" class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#editModal">
                                        <i class="fas fa-images me-2"></i> <span class="d-none d-sm-inline">{{__("Open Image Gallery")}}</span><span class="d-sm-none">{{__("Gallery")}}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0 fw-bold text-white">
                            <i class="fas fa-file-upload me-2"></i> {{__("Upload & Process Updated CSV File")}}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="{{ route('custom-fields.bulk-update.process') }}" method="POST" enctype="multipart/form-data" id="bulkUpdateForm">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label for="excel_file" class="form-label fw-bold">
                                            {{__("SelectFile")}} <span class="text-danger">*</span>
                                        </label>
                                       <input type="file" name="excel_file" id="excel_file" class="form-control form-control-lg" accept=".xlsx,.xls,.csv" required style="touch-action: manipulation; -webkit-touch-callout: none;">
                                        <div class="form-text">
                                            <i class="fas fa-info-circle me-1"></i>
                                            {{__("Supported formats: CSV, XLSX, XLS")}}
                                        </div>
                                    </div>
                                    <div class="alert alert-light border d-flex align-items-start mb-3">
                                        <i class="fas fa-lightbulb text-warning me-2 mt-1"></i>
                                        <div>
                                            <strong class="d-block mb-1">{{__("Important:")}}</strong>
                                            <p class="mb-0 small">{{__("Make sure your CSV file includes the ID column and follows the exact format from the downloaded file. You can open CSV in Excel, edit it, and save. Invalid data will be skipped during processing.")}}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100 w-md-auto">
                                        <i class="fas fa-upload"></i> <span class="d-none d-sm-inline">{{__("Upload and Update")}}</span><span class="d-sm-none">{{__("Upload")}}</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
         <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="galleryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content shadow-lg">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold" id="galleryModalLabel">
                            <i class="fas fa-images me-2"></i> {{__("Image Gallery")}}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-upload text-primary me-2"></i> {{__("Upload New Images")}}
                                </h6>
                    <form id="galleryUploadForm" enctype="multipart/form-data">
                        @csrf
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            
                            <div class="flex-grow-1">
                                <input type="file" name="image" id="galleryImageInput" 
                                    class="form-control form-control-lg" 
                                    accept=".jpg,.jpeg,.png,.svg" multiple 
                                    style="min-height: 48px;">
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg" id="galleryUploadBtn">
                                <i class="fas fa-upload me-2"></i> 
                                <span class="d-none d-sm-inline">{{__("Upload Images")}}</span>
                                <span class="d-sm-none">{{__("Upload")}}</span>
                            </button>
                            
                        </div>

                        <div class="form-text mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            {{__("You can select multiple images at once. Supported formats: JPG, JPEG, PNG, SVG (Maximum size: 5MB per image)")}}
                        </div>
                    </form>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-images text-success me-2"></i> {{__("Uploaded Images")}}
                            </h6>
                        </div>
                        <div id="galleryImagesList" class="row g-3" style="max-height: 500px; overflow-y: auto; padding: 10px;">
                            {{-- Gallery images will be loaded here via AJAX --}}
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> {{__("Close")}}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Error Details Modal --}}
        <div class="modal fade" id="errorDetailsModal" tabindex="-1" role="dialog" aria-labelledby="errorModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content shadow-lg">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold" id="errorModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i> {{__("Bulk Update Errors")}}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info d-flex align-items-center mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>{{__("Summary:")}}</strong>
                                <span id="errorSummary"></span>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 500px;">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th style="width: 100px;">{{__("Row #")}}</th>
                                        <th>{{__("Error Message")}}</th>
                                    </tr>
                                </thead>
                                <tbody id="errorTableBody">
                                    {{-- Errors will be populated here --}}
                                </tbody>
                            </table>
                        </div>
                        <div id="moreErrorsNotice" class="alert alert-warning mt-3" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>{{__("Note:")}}</strong> {{__("Only the first 100 errors are displayed. There may be more errors in your file.")}}
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> {{__("Close")}}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('js')
<script>
        $(document).ready(function() {
            let isUploading = false; // Flag to prevent multiple simultaneous uploads
            
            // Handle gallery image upload
            $('#galleryUploadForm').on('submit', function(e) {
                e.preventDefault();
                
                // Prevent multiple simultaneous uploads
                if (isUploading) {
                    showErrorToast('{{__("Please wait for the current upload to complete")}}');
                    return false;
                }
                
                const formData = new FormData(this);
                const files = $('#galleryImageInput')[0].files;
                const submitBtn = $('#galleryUploadBtn');
                const originalBtnHtml = submitBtn.html();

                if (files.length === 0) {
                    showErrorToast('{{__("Please select at least one image")}}');
                    return false;
                }

                // Set uploading flag and disable button
                isUploading = true;
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> <span class="d-none d-sm-inline">{{__("Uploading...")}}</span><span class="d-sm-none">{{__("Uploading")}}</span>');

                // Add all files to FormData
                for (let i = 0; i < files.length; i++) {
                    formData.append('images[]', files[i]);
                }

                $.ajax({
                    url: '{{ route("custom-fields.bulk-upload.gallery.upload") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.status === 'success') {
                            showSuccessToast(response.message || '{{__("Images uploaded successfully")}}');
                            // Clear file input to prevent duplicate uploads
                            $('#galleryImageInput').val('');
                            // Reset form to clear any cached file data
                            $('#galleryUploadForm')[0].reset();
                            loadGalleryImages();
                        } else {
                            showErrorToast(response.message || '{{__("Error uploading images")}}');
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || '{{__("Error uploading images")}}';
                        showErrorToast(errorMsg);
                    },
                    complete: function() {
                        // Reset uploading flag and re-enable button
                        isUploading = false;
                        submitBtn.prop('disabled', false).html(originalBtnHtml);
                    }
                });
                
                return false;
            });
        });

        function loadGalleryImages() {
            $.ajax({
                url: '{{ route("custom-fields.bulk-upload.gallery.list") }}',
                type: 'GET',
                success: function(response) {
                    if (response.status === 'success' && response.images) {
                        let html = '';
                        if (response.images.length === 0) {
                            html = `
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-images text-muted" style="font-size: 48px;"></i>
                                        <p class="text-muted mt-3 mb-0">{{__("No images uploaded yet. Upload images using the form above.")}}</p>
                                    </div>
                                </div>
                            `;
                        } else {
                            response.images.forEach(function(image) {
                                html += `
                                    <div class="col-6 col-md-4 col-lg-3 mb-3">
                                        <div class="card h-100 shadow-sm border">
                                            <div class="position-relative">
                                                <img src="${image.url}" class="card-img-top" alt="Gallery Image" style="height: 200px; object-fit: cover; cursor: pointer; width: 100%;" onclick="copyImagePath('${image.path}')">
                                                <div class="position-absolute top-0 end-0 m-2">
                                                    <span class="badge bg-dark opacity-75">
                                                        <i class="fas fa-image"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-body p-2 p-md-3">
                                                <label class="form-label small fw-bold mb-2 d-block">{{__("Image Path:")}}</label>
                                                <input type="text" class="form-control form-control-sm mb-2 image-path-input" value="${image.path}" readonly onclick="this.select();" style="font-size: 11px;">
                                                <button type="button" class="btn btn-sm btn-primary w-100 copy-path-btn" data-path="${image.path}">
                                                    <i class="fas fa-copy me-1"></i> <span class="d-none d-sm-inline">{{__("Copy Path")}}</span><span class="d-sm-none">{{__("Copy")}}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        $('#galleryImagesList').html(html);
                    } else {
                        $('#galleryImagesList').html(`
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-images text-muted" style="font-size: 48px;"></i>
                                    <p class="text-muted mt-3 mb-0">{{__("No images uploaded yet. Upload images using the form above.")}}</p>
                                </div>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#galleryImagesList').html(`
                        <div class="col-12">
                            <div class="alert alert-danger text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                {{__("Error loading images. Please try again.")}}
                            </div>
                        </div>
                    `);
                }
            });
        }

        // Helper function to copy image path
        async function copyImagePath(path) {
            try {
                // Try modern Clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(path);
                } else {
                    // Fallback for older browsers
                    const input = document.createElement('input');
                    input.value = path;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    input.setSelectionRange(0, 99999); // For mobile devices
                    document.execCommand('copy');
                    document.body.removeChild(input);
                }
                
                // Show feedback
                const btn = $(`.copy-path-btn[data-path="${path}"]`);
                const originalHtml = btn.html();
                btn.html('<i class="fas fa-check me-1"></i> {{__("Copied!")}}').removeClass('btn-primary').addClass('btn-success');
                setTimeout(() => {
                    btn.html(originalHtml).removeClass('btn-success').addClass('btn-primary');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
                showErrorToast('{{__("Failed to copy path to clipboard")}}');
            }
        }

        // Copy path to clipboard
        $(document).on('click', '.copy-path-btn', function() {
            const path = $(this).data('path');
            copyImagePath(path);
        });
        
        // Also handle click on image to copy path
        $(document).on('click', '.card-img-top', function() {
            const pathInput = $(this).closest('.card').find('.image-path-input');
            if (pathInput.length) {
                const path = pathInput.val();
                copyImagePath(path);
            }
        });

        // Load gallery when modal is opened
        $('#editModal').on('show.bs.modal', function() {
            loadGalleryImages();
        });

        // Unbind default create-form handler to prevent duplicate messages
        $('#bulkUpdateForm').off('submit');
        
        // Handle bulk update form submission
        $('#bulkUpdateForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i> {{__("Processing...")}}').attr('disabled', true);
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    submitBtn.html(originalBtnText).attr('disabled', false);
                    
                    if (response.error) {
                        showErrorToast(response.message || '{{__("Error processing file")}}');
                        if (response.data && response.data.errors && response.data.errors.length > 0) {
                            displayErrors(response.data);
                        }
                    } else {
                        if (response.warning) {
                            showWarningToast(response.message || '{{__("Update completed with errors")}}');
                        } else {
                            showSuccessToast(response.message || '{{__("Update completed successfully")}}');
                        }
                        
                        // Show errors if any
                        if (response.data && response.data.errors && response.data.errors.length > 0) {
                            displayErrors(response.data);
                        }
                        
                        // Reset form on complete success
                        if (response.data && response.data.error_count === 0) {
                            $('#bulkUpdateForm')[0].reset();
                        }
                    }
                },
                error: function(xhr) {
                    submitBtn.html(originalBtnText).attr('disabled', false);
                    const errorMsg = xhr.responseJSON?.message || '{{__("Error uploading file")}}';
                    showErrorToast(errorMsg);
                    
                    if (xhr.responseJSON?.data && xhr.responseJSON.data.errors && xhr.responseJSON.data.errors.length > 0) {
                        displayErrors(xhr.responseJSON.data);
                    }
                }
            });
        });

        function displayErrors(data) {
            const errorCount = data.error_count || 0;
            const successCount = data.success_count || 0;
            const totalProcessed = data.total_processed || 0;
            const errors = data.errors || [];
            const hasMoreErrors = data.has_more_errors || false;
            
            // Update summary
            let summaryText = '';
            if (successCount > 0 && errorCount > 0) {
                summaryText = `{{__("Successfully updated")}} ${successCount} {{__("row(s)")}}, ${errorCount} {{__("error(s) found")}}`;
            } else if (errorCount > 0) {
                summaryText = `${errorCount} {{__("error(s) found")}}`;
            }
            $('#errorSummary').text(summaryText);
            
            // Populate error table
            const tbody = $('#errorTableBody');
            tbody.empty();
            
            if (errors.length === 0) {
                tbody.html('<tr><td colspan="2" class="text-center text-muted">{{__("No errors to display")}}</td></tr>');
            } else {
                errors.forEach(function(error) {
                    const row = $('<tr>');
                    row.append($('<td>').text(error.row || '-'));
                    row.append($('<td>').text(error.message || '-'));
                    tbody.append(row);
                });
            }
            
            // Show/hide more errors notice
            if (hasMoreErrors) {
                $('#moreErrorsNotice').show();
            } else {
                $('#moreErrorsNotice').hide();
            }
            
            // Show modal
            $('#errorDetailsModal').modal('show');
        }
</script>
@endsection

