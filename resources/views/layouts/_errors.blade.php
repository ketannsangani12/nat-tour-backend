<div class="py-3">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="alert alert-danger flex items-center justify-between !mb-0">
					<ul>
						@foreach($errors->all() as $error)
						<li>{{ $error }}</li>
						@endforeach
					</ul>
					<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
							<path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
						</svg></button>
				</div>
			</div>
		</div>
	</div>
</div>