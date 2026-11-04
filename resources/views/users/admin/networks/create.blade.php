@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-83px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Create New Network</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-outline-primary-small" href="{{ route(routePrefix() . '.networks.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

                    <div class="main-content">

                        {{-- Create Network Form --}}
                        <form action="{{ route(routePrefix() . '.networks.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('POST')
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <label for="name" class="form-label form-custom-label">Network Name</label>
                                    <input type="text" name="name" class="form-control form-custom-input" placeholder="Enter network name" required value="{{ old('name') }}">
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <label for="frontend_url" class="form-label form-custom-label">Frontend URL</label>
                                    <input type="url" name="frontend_url" class="form-control form-custom-input" placeholder="https://example.com" required value="{{ old('frontend_url') }}">
                                    @error('frontend_url')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <label for="status" class="form-label form-custom-label">Status</label>
                                    <select name="status" class="form-select form-custom-input">
                                        <option value="1" selected>Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center">
                                <button type="submit" class="btn btn-primary btn-primary-small mr-2">Create Network</button>
                                <a href="{{ route(routePrefix() . '.networks.index') }}" class="btn btn-secondary btn-light-small">Cancel</a>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
